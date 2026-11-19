<?php

namespace Tests\Feature;

use App\Jobs\SendEssentialEmail;
use App\Models\AccountInvitation;
use App\Models\EssentialEmail;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\Role;
use App\Models\SupervisorAssignment;
use App\Models\User;
use App\Services\AuditService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Queue;
use Laravel\Fortify\Features;
use RuntimeException;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mime\Email;
use Tests\Support\AttendanceData;
use Tests\Support\Phase3Data;
use Tests\TestCase;

class EssentialEmailTest extends TestCase
{
    use AttendanceData, Phase3Data, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['mail.default' => 'smtp', 'mail.mailers.smtp.transport' => 'array', 'mail.essential.link_base' => 'https://attendance.example.test']);
        Queue::fake([SendEssentialEmail::class]);
        config(['fortify.features' => [Features::resetPasswords()]]);
        require base_path('vendor/laravel/fortify/routes/routes.php');
        app('router')->getRoutes()->refreshNameLookups();
        app('router')->getRoutes()->refreshActionLookups();
    }

    public function test_new_employee_setup_is_queued_once_and_uses_the_existing_activation_token(): void
    {
        $this->actingAs($this->hr());
        $input = $this->employeeData();

        $this->post(route('hr.employees.store'), $input)->assertRedirect()->assertSessionHasNoErrors();
        $delivery = EssentialEmail::sole();

        Queue::assertPushed(SendEssentialEmail::class, fn (SendEssentialEmail $job): bool => $job->emailId === $delivery->id && $job->connection === 'database' && $job->queue === 'mail' && $job->afterCommit);
        $this->assertSame($input['work_email'], $delivery->recipient_email);
        $this->assertStringNotContainsString($delivery->auth_token, $delivery->getRawOriginal('auth_token'));
        $this->assertSame(hash('sha256', $delivery->auth_token), AccountInvitation::sole()->token_hash);
        $delivery->update(['link_base' => 'https://retired-tunnel.trycloudflare.com']);
        $this->assertSame(0, $this->transportMessages()->count());
        $this->process($delivery);
        $this->process($delivery);

        $mail = $this->sentEmail();
        $this->assertSame('Set up your LIMCOMA employee account', $mail->getSubject());
        $this->assertSame([$input['work_email']], array_map(fn ($to) => $to->getAddress(), $mail->getTo()));
        $this->assertStringContainsString($input['employee_number'], $mail->getHtmlBody());
        $this->assertStringContainsString($input['work_email'], $mail->getHtmlBody());
        $this->assertStringContainsString('https://attendance.example.test/activate-account#'.$delivery->auth_token, $mail->getHtmlBody());
        $this->assertStringNotContainsString($delivery->recipient->password, $mail->getHtmlBody());
        $this->assertSame('submitted', $delivery->fresh()->status);
        $this->assertSame(1, $delivery->fresh()->attempts);
        $this->assertNull($delivery->fresh()->auth_token);
    }

    public function test_rolled_back_employee_creation_discards_the_email_and_its_job(): void
    {
        $this->actingAs($this->hr());
        $input = $this->employeeData();
        Exceptions::fake();
        $this->partialMock(AuditService::class, function ($mock): void {
            $mock->shouldReceive('record')->withArgs(fn ($action) => $action === 'employee.created')
                ->andThrow(new RuntimeException('Synthetic post-invitation audit failure'));
        });

        $this->post(route('hr.employees.store'), $input)->assertServerError();

        Queue::assertNotPushed(SendEssentialEmail::class);
        $this->assertDatabaseEmpty('essential_emails');
        $this->assertDatabaseEmpty('account_invitations');
    }

    public function test_fortify_queues_one_password_reset_for_the_owner_and_keeps_generic_responses(): void
    {
        $owner = User::factory()->create();

        $known = $this->postJson('/forgot-password', ['email' => $owner->email])->assertOk()->json('message');
        $unknown = $this->postJson('/forgot-password', ['email' => 'unknown@example.test'])->assertOk()->json('message');

        $this->assertSame($known, $unknown);
        Queue::assertPushed(SendEssentialEmail::class, 1);
        $delivery = EssentialEmail::sole();
        $this->assertTrue(Password::broker()->tokenExists($owner, $delivery->auth_token));
        $this->process($delivery);

        $mail = $this->sentEmail();
        $this->assertSame($owner->email, $mail->getTo()[0]->getAddress());
        $this->assertSame('Your LIMCOMA password reset request', $mail->getSubject());
        $this->assertStringContainsString('/reset-password/'.$delivery->auth_token, $mail->getHtmlBody());
        $this->assertStringContainsString('If you did not request a reset', $mail->getHtmlBody());
        $this->assertNull($delivery->fresh()->auth_token);
    }

    public function test_submit_emails_only_the_authorized_supervisor_and_rejection_only_the_owner(): void
    {
        $employee = $this->attendee();
        $supervisor = User::factory()->create(['role_id' => Role::SUPERVISOR, 'supervisor_department_id' => $employee->department_id]);
        User::factory()->create(['role_id' => Role::SUPERVISOR, 'supervisor_department_id' => $employee->department_id]);
        $this->hr();
        SupervisorAssignment::factory()->create(['employee_id' => $employee->id, 'supervisor_user_id' => $supervisor->id]);
        $this->submitLeave($employee->user);
        $leave = LeaveRequest::sole();
        $delivery = EssentialEmail::sole();

        $this->process($delivery);

        $submission = $this->sentEmail();
        $this->assertSame($supervisor->email, $submission->getTo()[0]->getAddress());
        $this->assertCount(1, $submission->getTo());
        $this->assertSame('LIMCOMA leave application awaiting your review', $submission->getSubject());
        $this->assertStringContainsString('/supervisor/leave/'.$leave->id, $submission->getHtmlBody());

        $remarks = 'Insufficient documentation <reviewed>';
        $decision = ['decision' => 'rejected', 'remarks' => $remarks, 'confirmation' => 1];
        $this->actingAs($supervisor)->postJson(route('supervisor.leave.decide', $leave), $decision)->assertRedirect();
        $this->postJson(route('supervisor.leave.decide', $leave), $decision)->assertConflict();
        $rejection = EssentialEmail::where('type', EssentialEmail::LEAVE_REJECTED)->sole();
        $this->process($rejection);
        $this->process($rejection);

        $messages = $this->transportMessages();
        $this->assertCount(2, $messages);
        $mail = $messages->last()->getOriginalMessage();
        $this->assertSame($employee->work_email, $mail->getTo()[0]->getAddress());
        $this->assertStringContainsString(e($remarks), $mail->getHtmlBody());
        $this->assertBranding($mail);
        $this->assertStringContainsString($remarks, $mail->getTextBody());
    }

    public function test_unassigned_leave_and_routine_attendance_do_not_queue_mail(): void
    {
        $employee = $this->attendee();
        $this->submitLeave($employee->user);
        $this->punch($this->capture($employee))->assertOk();

        Queue::assertNotPushed(SendEssentialEmail::class);
        $this->assertDatabaseEmpty('essential_emails');
        $this->assertSame('awaiting_supervisor_assignment', LeaveRequest::sole()->status);
    }

    public function test_unsure_transport_errors_are_redacted_and_duplicate_jobs_do_not_resend(): void
    {
        $owner = User::factory()->create();
        $owner->sendPasswordResetNotification(Password::broker()->createToken($owner));
        $delivery = EssentialEmail::sole();
        Mail::shouldReceive('mailer')->once()->with('smtp')->andThrow(new TransportException('Synthetic sensitive diagnostic user@example.test'));

        $this->process($delivery);
        $this->process($delivery);

        $this->assertSame('uncertain', $delivery->fresh()->status);
        $this->assertSame('submission_outcome_unknown', $delivery->fresh()->failure_reason);
        $this->assertSame(1, $delivery->fresh()->attempts);
        $this->assertNull($delivery->fresh()->auth_token);
    }

    public function test_a_definite_temporary_smtp_rejection_is_retryable(): void
    {
        $owner = User::factory()->create();
        $owner->sendPasswordResetNotification(Password::broker()->createToken($owner));
        $delivery = EssentialEmail::sole();
        Mail::shouldReceive('mailer')->once()->with('smtp')->andThrow(new TransportException('Synthetic diagnostic', 421));

        try {
            $this->process($delivery);
            $this->fail('The worker should retry a temporary SMTP rejection.');
        } catch (RuntimeException $exception) {
            $this->assertSame('smtp_temporary_rejection', $exception->getMessage());
        }

        $this->assertSame('retrying', $delivery->fresh()->status);
        $this->assertNotNull($delivery->fresh()->auth_token);
    }

    private function process(EssentialEmail $delivery): void
    {
        app()->call([new SendEssentialEmail($delivery->id), 'handle']);
    }

    private function submitLeave(User $owner): void
    {
        $date = now()->addWeek()->toDateString();
        $type = LeaveType::factory()->create();

        $this->actingAs($owner)->post(route('employee.leave.store'), [
            'leave_type_id' => $type->id, 'start_date' => $date, 'end_date' => $date,
            'reason' => 'An authorized synthetic leave request to test the email workflow.',
        ])->assertRedirect()->assertSessionHasNoErrors();
    }

    private function transportMessages(): Collection
    {
        return Mail::mailer('smtp')->getSymfonyTransport()->messages();
    }

    private function assertBranding(Email $message): void
    {
        $this->assertCount(0, $message->getAttachments());
        $this->assertStringContainsString('src="https://attendance.example.test/LIMCOMA-email.png"', $message->getHtmlBody());
        $this->assertStringContainsString('Employee Attendance and Management System', $message->getTextBody());
        $this->assertSame(config('mail.from.address'), $message->getFrom()[0]->getAddress());
    }

    public function test_production_rejects_a_temporary_quick_tunnel(): void
    {
        config(['mail.essential.link_base' => 'https://temporary.trycloudflare.com']);
        $this->actingAs($this->hr())->post(route('hr.employees.store'), $this->employeeData())->assertSessionHasNoErrors();
        $delivery = EssentialEmail::sole();
        $this->app->instance('env', 'production');
        $this->process($delivery);
        $this->assertSame('invalid_application_link_base', $delivery->fresh()->failure_reason);
        $this->assertCount(0, $this->transportMessages());
    }

    public function test_external_delivery_rejects_a_localhost_setup_link(): void
    {
        config(['mail.essential.link_base' => 'https://localhost']);
        $this->actingAs($this->hr())->post(route('hr.employees.store'), $this->employeeData())->assertSessionHasNoErrors();
        $delivery = EssentialEmail::sole();
        $this->process($delivery);
        $this->assertSame('invalid_application_link_base', $delivery->fresh()->failure_reason);
        $this->assertCount(0, $this->transportMessages());
    }

    private function sentEmail(): Email
    {
        $message = $this->transportMessages()->sole()->getOriginalMessage();
        $this->assertBranding($message);

        return $message;
    }
}
