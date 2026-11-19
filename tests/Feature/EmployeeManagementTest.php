<?php

namespace Tests\Feature;

use App\Jobs\SendEssentialEmail;
use App\Mail\AccountInvitationMail;
use App\Models\AccountInvitation;
use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\EssentialEmail;
use App\Models\Role;
use App\Models\User;
use App\Services\AuditService;
use App\Services\EditVersion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Symfony\Component\Mailer\Exception\TransportException;
use Tests\Support\Phase3Data;
use Tests\TestCase;

class EmployeeManagementTest extends TestCase
{
    use Phase3Data, RefreshDatabase;

    public function test_hr_creation_is_atomic_and_fixed_to_pending_employee(): void
    {
        $this->actingAs($this->hr());
        $data = $this->employeeData();
        $employee = $this->registerEmployee($data);
        $this->assertSame(Role::EMPLOYEE, $employee->user->role_id);
        $this->assertSame('pending_activation', $employee->user->account_status);
        $this->assertSame($data['branch_id'], $employee->currentAssignment->branch_id);
        $this->assertSame($data['work_email'], $employee->work_email);
        $this->assertDatabaseCount('account_invitations', 1);
        Mail::assertSent(AccountInvitationMail::class);
        $this->get('/hr/employees/'.$employee->id)->assertOk()->assertSee('Synthetic Person');
        $this->assertTrue(AuditLog::where('action', 'employee.created')->exists());
    }

    public function test_duplicate_identifiers_and_role_injection_are_rejected(): void
    {
        $this->actingAs($this->hr());
        $data = $this->employeeData();
        $this->registerEmployee($data);
        $this->post('/hr/employees', $data)->assertSessionHasErrors(['employee_number', 'work_email']);
        $this->post('/hr/employees', [...$data, 'employee_number' => 'QA-OTHER', 'work_email' => 'other@example.test', 'role_id' => Role::HR])->assertSessionHasErrors('role_id');
        $this->assertDatabaseCount('employees', 1);
    }

    public function test_malformed_email_values_are_validation_errors_not_server_errors(): void
    {
        $this->actingAs($this->hr());
        $this->postJson('/hr/employees', [...$this->employeeData(), 'work_email' => ['invalid']])->assertUnprocessable()->assertJsonValidationErrors('work_email');
        $this->withSession(['auth.password_confirmed_at' => now()->timestamp])->postJson('/hr/hr-accounts', ['name' => 'Synthetic HR', 'email' => ['invalid']])->assertUnprocessable()->assertJsonValidationErrors('email');
        $this->assertDatabaseCount('users', 1);
    }

    public function test_edit_preserves_password_and_transfer_history_and_filters_real_records(): void
    {
        $this->actingAs($this->hr());
        $data = $this->employeeData();
        $employee = $this->registerEmployee($data);
        $hash = $employee->user->password;
        $next = Branch::factory()->create();
        $this->put('/hr/employees/'.$employee->id, [...$data, '_edit_version' => EditVersion::token($employee), 'branch_id' => $next->id, 'position_title' => 'Updated position'])->assertSessionHasNoErrors();
        $this->assertSame($hash, $employee->user->fresh()->password);
        $this->assertSame($next->id, $employee->fresh()->currentAssignment->branch_id);
        $this->assertSame(2, $employee->assignments()->count());
        $this->assertSame(1, $employee->assignments()->whereNotNull('effective_until')->count());
        $this->get('/hr/employees?branch_id='.$next->id.'&q='.$employee->employee_number)->assertOk()->assertSee($employee->employee_number);
        $this->get('/hr/employees?sort=password')->assertSessionHasErrors('sort');
    }

    public function test_deactivation_revokes_invitations_and_sessions_and_requires_reactivation(): void
    {
        $this->actingAs($this->hr());
        $employee = $this->registerEmployee($this->employeeData());
        $this->patch('/hr/employees/'.$employee->id.'/status', ['action' => 'deactivate', 'confirmation' => 1])->assertSessionHasNoErrors();
        $this->assertSame('deactivated', $employee->user->fresh()->account_status);
        $this->assertNotNull(AccountInvitation::sole()->revoked_at);
        $this->patch('/hr/employees/'.$employee->id.'/status', ['action' => 'reactivate', 'confirmation' => 1])->assertSessionHasNoErrors();
        $this->assertSame('pending_activation', $employee->user->fresh()->account_status);
        $this->assertDatabaseCount('account_invitations', 2);
    }

    public function test_audit_failure_rolls_back_user_employee_assignment_and_invitation(): void
    {
        $this->actingAs($this->hr());
        $data = $this->employeeData();
        Mail::fake();
        Exceptions::fake();
        $this->mock(AuditService::class)->shouldReceive('record')->andThrow(new \RuntimeException('Synthetic audit failure'));
        $this->post('/hr/employees', $data)->assertServerError();
        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseCount('employees', 0);
        $this->assertDatabaseCount('employee_branch_assignments', 0);
        $this->assertDatabaseCount('account_invitations', 0);
        Mail::assertNothingSent();
    }

    public function test_delivery_failure_retains_pending_account_without_false_success(): void
    {
        $this->actingAs($this->hr());
        $data = $this->employeeData();
        config(['mail.default' => 'smtp', 'mail.mailers.smtp.transport' => 'array']);
        Queue::fake([SendEssentialEmail::class]);
        Mail::shouldReceive('mailer')->with('smtp')->andThrow(new TransportException('Synthetic SMTP authentication failure', 535));
        $this->post('/hr/employees', $data)->assertRedirect()->assertSessionHas('status', fn ($message) => str_contains($message, 'scheduled'));
        $delivery = EssentialEmail::sole();
        $this->assertSame('queued', $delivery->status);

        app()->call([new SendEssentialEmail($delivery->id), 'handle']);

        $this->assertSame('smtp_authentication_failed', $delivery->fresh()->failure_reason);
        $this->assertSame('pending_activation', User::where('email', $data['work_email'])->firstOrFail()->account_status);
        $this->assertSame('failed', AccountInvitation::sole()->delivery_status);
    }

    public function test_existing_sessions_and_stale_reset_tokens_are_revoked_on_account_changes(): void
    {
        $this->actingAs($this->hr());
        $data = $this->employeeData();
        $employee = $this->registerEmployee($data);
        DB::table('sessions')->insert(['id' => 'synthetic-session', 'user_id' => $employee->user_id, 'payload' => base64_encode(serialize([])), 'last_activity' => now()->timestamp]);
        DB::table('password_reset_tokens')->insert(['email' => $data['work_email'], 'token' => 'synthetic-hash', 'created_at' => now()]);
        $oldVersion = $employee->user->session_version;
        $this->put('/hr/employees/'.$employee->id, [...$data, '_edit_version' => EditVersion::token($employee), 'work_email' => 'replacement@example.test'])->assertSessionHasNoErrors();
        $this->assertDatabaseMissing('sessions', ['id' => 'synthetic-session']);
        $this->assertDatabaseMissing('password_reset_tokens', ['email' => $data['work_email']]);
        $this->assertGreaterThan($oldVersion, $employee->user->fresh()->session_version);
        $this->assertSame('pending_activation', $employee->user->fresh()->account_status);
    }
}
