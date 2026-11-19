<?php

namespace Tests\Feature;

use App\Jobs\SendEssentialEmail;
use App\Models\Employee;
use App\Models\EmployeeLeaveBalance;
use App\Models\EssentialEmail;
use App\Models\LeaveApprovalDocument;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\Role;
use App\Models\SupervisorAssignment;
use App\Models\SupervisorSignature;
use App\Models\User;
use App\Services\LeavePdfRenderer;
use App\Services\SupervisorSignatureService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestWith;
use RuntimeException;
use Tests\Support\AttendanceData;
use Tests\TestCase;

class OfficialLeaveFormTest extends TestCase
{
    use AttendanceData, RefreshDatabase;

    private const TEST_PDF = "%PDF-1.4\n%%EOF";

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('leave');
    }

    public static function categories(): array
    {
        return [
            'vacation' => ['Vacation Leave', null, 'Vacation Leave', null],
            'sick' => ['Sick Leave', null, 'Sick Leave', null],
            'configured specific' => ['Bereavement Leave', null, 'Others (pls specify)', 'Bereavement Leave'],
            'configured generic' => ['Others', 'Official examination', 'Others (pls specify)', 'Official examination'],
        ];
    }

    #[DataProvider('categories')]
    public function test_unsigned_form_uses_saved_submission_and_marks_the_configured_leave_type(string $typeName, ?string $specification, string $checkbox, ?string $other): void
    {
        $employee = $this->attendee();
        $this->assign($employee);
        $type = LeaveType::factory()->create(['name' => $typeName, 'allows_half_day' => true]);
        $date = now()->addWeek();
        $leave = $this->submit($employee, $type, [
            'start_date' => $date->toDateString(),
            'end_date' => $date->toDateString(),
            'requested_duration' => 0.5,
            'half_day' => 'am',
            'other_leave_type' => $specification,
        ]);
        $filed = now(config('attendance.timezone'))->format('M j, Y');
        $department = $employee->department->department_name;
        $employee->department->update(['department_name' => 'Renamed after submission']);
        $type->update(['name' => 'Renamed leave type']);

        $preview = $this->get(route('employee.leave.form', $leave))->assertOk()
            ->assertSee(['LMPC', 'LEAVE APPLICATION FORM', 'HRD No. 23/Rev.2', 'Leave Application — Pending Approval'])
            ->assertSee([$employee->full_name, $department, $filed, $date->format('M j, Y'), '0.5 (AM)', $leave->reason])
            ->assertSee('aria-label="Selected">✓</span> <strong>'.$checkbox.'</strong>', false)
            ->assertDontSee('Renamed after submission')
            ->assertSee('class="form-watermark" src="'.asset('LIMCOMA.png').'"', false)
            ->assertDontSee('alt="Supervisor signature"', false);
        $this->assertSame(1, substr_count($preview->getContent(), 'aria-label="Selected"'));
        if ($other !== null) {
            $preview->assertSee($other);
        }
    }

    public function test_paper_application_uses_profile_and_server_date_and_calculates_inclusive_days(): void
    {
        $employee = $this->attendee();
        $type = LeaveType::factory()->create(['name' => 'Vacation Leave']);
        LeaveType::factory()->create(['name' => 'Sick Leave']);
        LeaveType::factory()->create(['name' => 'Others']);

        $this->actingAs($employee->user)->get(route('employee.leave.create'))->assertOk()
            ->assertSee([$employee->full_name, $employee->department->department_name, now(config('attendance.timezone'))->format('M j, Y')])
            ->assertSee(['LEAVE APPLICATION FORM', 'HRD No. 23/Rev.2', 'Vacation Leave', 'Sick Leave', 'Others (pls specify)'])
            ->assertDontSee('name="requested_duration"', false);

        $data = $this->application($type, [
            'leave_category' => 'sick',
            'end_date' => now()->addDays(9)->toDateString(),
            'requested_duration' => 1,
        ]);
        $this->postJson(route('employee.leave.store'), $data)->assertUnprocessable()->assertJsonValidationErrors('leave_type_id');
        $this->post(route('employee.leave.store'), array_replace($data, ['leave_category' => 'vacation']))
            ->assertRedirect()->assertSessionHasNoErrors();

        $leave = LeaveRequest::sole();
        $this->assertSame('3.00', $leave->requested_duration);
        $this->assertSame(now()->startOfSecond()->timestamp, $leave->submitted_at->timestamp);
        $this->assertSame('vacation', $leave->form_snapshot['category']);
    }

    public function test_generic_other_requires_a_description(): void
    {
        $employee = $this->attendee();
        $type = LeaveType::factory()->create(['name' => 'Others']);
        $this->actingAs($employee->user)->postJson(route('employee.leave.store'), $this->application($type, ['leave_category' => 'other']))
            ->assertUnprocessable()->assertJsonValidationErrors('other_leave_type');
    }

    public function test_only_active_supervisor_can_register_and_read_their_own_valid_signature(): void
    {
        $employee = $this->attendee();
        $supervisor = $this->assign($employee);

        $this->actingAs($supervisor)->postJson(route('supervisor.signature.store'), [
            'current_password' => 'wrong',
            'signature' => UploadedFile::fake()->image('signature.png', 160, 45),
        ])->assertUnprocessable()->assertJsonValidationErrors('current_password');
        foreach ([
            UploadedFile::fake()->createWithContent('signature.png', '<svg></svg>'),
            UploadedFile::fake()->image('signature.png', 5, 40),
            UploadedFile::fake()->image('signature.png', 160, 45)->size(2049),
        ] as $file) {
            $this->postJson(route('supervisor.signature.store'), [
                'current_password' => 'password',
                'signature' => $file,
            ])->assertUnprocessable()->assertJsonValidationErrors('signature');
        }

        $this->actingAs($supervisor)->get(route('supervisor.signature.show'))->assertOk()->assertSee('Save signature');
        $this->enroll($supervisor);
        $this->get(route('supervisor.signature.show'))->assertOk()->assertSee('Replace signature');
        $signature = SupervisorSignature::sole();
        $image = $this->get(route('supervisor.signature.image'))->assertOk()->assertHeader('Content-Type', 'image/png')->getContent();
        $this->assertNotSame($image, Storage::disk('leave')->get($signature->storage_path));
        $this->assertDatabaseHas('audit_logs', ['action' => 'signature.registered', 'actor_user_id' => $supervisor->id]);

        foreach ([$employee->user, $this->reviewer()] as $unauthorized) {
            $this->actingAs($unauthorized)->get(route('supervisor.signature.image'))->assertForbidden();
            $this->postJson(route('supervisor.signature.store'), ['current_password' => 'password'])->assertForbidden();
        }
        $this->actingAs(User::factory()->create(['role_id' => Role::SUPERVISOR]))
            ->get(route('supervisor.signature.image'))->assertNotFound();
    }

    public function test_approved_document_is_generated_once_scoped_and_unchanged_by_signature_replacement(): void
    {
        $employee = $this->attendee();
        $supervisor = $this->assign($employee);
        $type = LeaveType::factory()->create(['name' => 'Vacation Leave', 'affects_balance' => true]);
        $leave = $this->submit($employee, $type);
        $balance = EmployeeLeaveBalance::factory()->create([
            'employee_id' => $employee->id, 'leave_type_id' => $type->id,
            'policy_period' => $leave->start_date->format('Y'), 'available_days' => 5,
        ]);
        $url = route('supervisor.leave.decide', $leave);
        $this->actingAs($supervisor)->postJson($url, $this->approval())->assertUnprocessable()->assertJsonValidationErrors('decision');
        $this->enroll($supervisor);
        $this->get(route('supervisor.leave.show', $leave))->assertOk()->assertSee('Approve &amp; Sign', false);
        $this->postJson($url, $this->approval(['current_password' => 'wrong']))->assertUnprocessable()->assertJsonValidationErrors('current_password');
        $this->postJson($url, $this->approval(['confirmation' => 0]))->assertUnprocessable()->assertJsonValidationErrors('confirmation');
        $this->postJson($url, $this->approval(['signature_version' => 2]))->assertUnprocessable()->assertJsonValidationErrors('decision');

        $this->mock(LeavePdfRenderer::class, function (MockInterface $mock) use ($supervisor): void {
            $mock->shouldReceive('render')->once()->withArgs(function (string $html) use ($supervisor): bool {
                $this->assertSame(1, preg_match('/class="form-watermark" src="data:image\/jpeg;base64,([^"]+)"/', $html, $watermark));
                $this->assertSame(hash_file('sha256', resource_path('images/limcoma-leave-watermark.jpg')), hash('sha256', base64_decode($watermark[1], true)));
                $this->assertStringContainsString('aria-label="Selected">✓</span> AUTHORIZED LEAVE', $html);
                $this->assertStringContainsString('aria-label="Unselected"></span> UNAUTHORIZED LEAVE', $html);
                $this->assertMatchesRegularExpression('/Approved by:.*data:image\/png;base64,.*alt="Supervisor signature".*'.preg_quote(e($supervisor->name), '/').'/s', $html);

                return true;
            })->andReturn(self::TEST_PDF);
        });
        $this->postJson($url, $this->approval())->assertRedirect();
        $this->postJson($url, $this->approval())->assertConflict();
        $document = $leave->approvalDocument()->sole();
        $this->assertSame($supervisor->id, $document->approved_by_user_id);
        $this->assertSame('LMPC-LA-'.$leave->id.'-01', $document->document_reference);
        $this->assertSame(hash('sha256', self::TEST_PDF), $document->sha256_hash);
        $this->assertSame('4.00', $balance->fresh()->available_days);
        $this->assertSame('approved', $leave->fresh()->status);
        $this->assertDatabaseCount('leave_approval_documents', 1);
        $this->assertDatabaseCount('leave_balance_transactions', 1);
        foreach (['leave.signing_confirmed', 'leave.official_pdf_generated', 'leave.approved'] as $event) {
            $this->assertDatabaseHas('audit_logs', ['actor_user_id' => $supervisor->id, 'action' => $event, 'subject_id' => $leave->id]);
        }

        $stored = Storage::disk('leave')->get($document->storage_path);
        $this->enroll($supervisor);
        $this->assertNotNull($document->signature->revoked_at);
        $this->assertSame(2, SupervisorSignature::activeFor($supervisor)->sole()->version);
        $this->assertSame($stored, Storage::disk('leave')->get($document->storage_path));

        $form = route('employee.leave.approved-form', $leave);
        $this->actingAs($employee->user)->get($form)->assertOk()->assertHeader('Content-Type', 'application/pdf')
            ->assertHeader('Content-Disposition', 'inline; filename='.$document->document_reference.'.pdf')->assertContent(self::TEST_PDF);
        $this->get($form.'?download=1')->assertDownload($document->document_reference.'.pdf');
        $this->put($form)->assertMethodNotAllowed();
        $this->assertDatabaseHas('audit_logs', ['actor_user_id' => $employee->user_id, 'action' => 'leave.approved_pdf_accessed', 'subject_id' => $leave->id]);
        $this->actingAs($this->reviewer())->get(route('hr.leave.approved-form', $leave))->assertOk();
        $this->actingAs(Employee::factory()->create()->user)->get($form)->assertForbidden();
        $this->actingAs(User::factory()->create(['role_id' => Role::SUPERVISOR]))->get(route('supervisor.leave.approved-form', $leave))->assertForbidden();

        Storage::disk('leave')->put($document->storage_path, Crypt::encryptString(self::TEST_PDF.' altered'));
        $this->actingAs($employee->user)->get($form)->assertServiceUnavailable();
    }

    public function test_qr_verification_authenticates_checks_integrity_and_tracks_document_versions(): void
    {
        config(['leave.verification_url' => 'https://verify.limcoma.test']);
        $employee = $this->attendee();
        $supervisor = $this->assign($employee);
        $hr = $this->reviewer();
        $leave = $this->submit($employee, LeaveType::factory()->create());
        $this->get(route('employee.leave.form', $leave))->assertOk()->assertDontSee('Scan to Verify');
        $this->enroll($supervisor);
        $tokens = [];
        $this->mock(LeavePdfRenderer::class, function (MockInterface $mock) use (&$tokens): void {
            $mock->shouldReceive('render')->twice()->andReturnUsing(function (string $html) use (&$tokens): string {
                $this->assertSame(1, preg_match('~href="https://verify\.limcoma\.test/verify/leave#([a-f0-9]{64})"~', $html, $matches));
                $this->assertStringContainsString('Scan to Verify', $html);
                $this->assertStringContainsString('data:image/svg+xml;base64,', $html);
                $tokens[] = $matches[1];

                return self::TEST_PDF;
            });
        });
        $this->postJson(route('supervisor.leave.decide', $leave), $this->approval())->assertRedirect();
        $first = $leave->approvalDocument()->sole();
        $this->assertSame(hash('sha256', $tokens[0]), $first->verification_token_hash);
        $show = route('leave.verify.show', $first);

        $this->post('/logout');
        $this->get(route('leave.verify.landing'))->assertOk()->assertDontSee($first->document_reference);
        $this->post(route('leave.verify.capture'), ['token' => $tokens[0]])
            ->assertRedirect(route('login'))->assertSessionHas('leave_scan_hash', $first->verification_token_hash);
        $this->post('/login', ['email' => $hr->email, 'password' => 'password'])->assertRedirect(route('leave.verify.scan'));
        $this->get(route('leave.verify.scan'))->assertRedirect($show);
        $this->get($show)->assertOk()->assertSee(['Valid Approved Record', 'Match', 'View Official PDF']);
        $this->get(route('leave.verify.search', ['reference' => $first->document_reference]))->assertRedirect($show);
        $this->get(route('leave.verify.original', $first))->assertOk()->assertContent(self::TEST_PDF);

        $unrelatedEmployee = Employee::factory()->create()->user;
        $this->actingAs($unrelatedEmployee)->get($show)->assertNotFound()->assertDontSee($first->document_reference);
        $this->get(route('leave.verify.original', $first))->assertNotFound();
        $this->actingAs($employee->user)->get($show)->assertOk()->assertSee('Valid Approved Record');
        $this->actingAs($supervisor)->get($show)->assertOk()->assertSee('Valid Approved Record');
        $this->post(route('leave.verify.capture'), ['token' => str_repeat('0', 64)])->assertRedirect();
        $this->get(route('leave.verify.scan'))->assertNotFound()->assertDontSee($first->document_reference);

        Storage::disk('leave')->put($first->storage_path, Crypt::encryptString('altered'));
        $this->get($show)->assertServiceUnavailable()->assertSee('Mismatch')->assertDontSee('Valid Approved Record');
        Storage::disk('leave')->put($first->storage_path, Crypt::encryptString(self::TEST_PDF));
        $decision = ['current_password' => 'password', 'confirmation' => 1, 'reason' => 'Reissue after documented administrative review.'];
        $this->actingAs($hr)->post(route('leave.verify.replace', $first), $decision + ['signature_version' => 1])->assertForbidden();
        $this->actingAs($supervisor)->post(route('leave.verify.replace', $first), $decision + ['signature_version' => 1])->assertRedirect();
        $second = $leave->approvalDocument()->sole();
        $this->assertSame(2, $second->version);
        $this->assertSame($second->id, $first->fresh()->superseded_by_document_id);
        $this->assertNotSame($first->verification_token_hash, $second->verification_token_hash);
        $this->assertNotSame($first->document_reference, $second->document_reference);
        $this->get($show)->assertOk()->assertSee('Previous Document Version');
        $this->get(route('leave.verify.original', $first))->assertOk()->assertContent(self::TEST_PDF);
        $secondUrl = route('leave.verify.show', $second);
        $this->get($secondUrl)->assertOk()->assertSee('Valid Approved Record');
        $this->actingAs($hr)->post(route('leave.verify.revoke', $second), $decision)->assertRedirect($secondUrl);
        $this->assertSame('revoked', $leave->fresh()->status);
        $this->post(route('leave.verify.revoke', $second), $decision)->assertConflict();
        $this->assertSame(1, $hr->unreadNotifications()->where('data->title', 'An approved leave request was revoked by HR.')->where('data->url', route('hr.leave.show', $leave))->count());
        $this->assertSame(1, $supervisor->unreadNotifications()->where('data->title', 'HR revoked an approved leave request assigned to you.')->where('data->url', route('supervisor.leave.show', $leave))->count());
        $this->assertSame(1, $employee->user->unreadNotifications()->where('data->title', 'HR revoked your approved leave request.')->where('data->url', route('employee.leave.show', $leave))->count());
        $this->getJson(route('notifications.dropdown.index'))->assertJsonFragment(['title' => 'An approved leave request was revoked by HR.', 'category' => 'leave']);
        $this->assertFalse($unrelatedEmployee->notifications()->exists());
        $this->assertDatabaseHas('leave_reviews', ['leave_request_id' => $leave->id, 'action' => 'revoked', 'reviewer_user_id' => $hr->id]);
        $this->get($secondUrl)->assertOk()->assertSee('Document Revoked');
        $this->get(route('leave.verify.original', $second))->assertOk()->assertContent(self::TEST_PDF);
        $this->get(route('hr.leave.index', ['status' => 'revoked']))->assertOk()->assertSee($employee->full_name);
        $this->actingAs($employee->user)->get(route('employee.leave.show', $leave))->assertOk()->assertSee('Revoked');
        $this->actingAs($supervisor)->get(route('supervisor.leave.history', ['status' => 'revoked']))->assertOk()->assertSee($employee->full_name);
        $this->getJson(route('notifications.dropdown.index'))->assertJsonFragment(['title' => 'HR revoked an approved leave request assigned to you.']);
        $this->post(route('leave.verify.replace', $second), $decision + ['signature_version' => 1])->assertConflict();
        $this->post(route('leave.verify.replace', $first), $decision + ['signature_version' => 1])->assertConflict();
        $this->assertDatabaseCount('leave_approval_documents', 2);
        $this->assertDatabaseCount('leave_balance_transactions', 0);
        $this->assertDatabaseHas('audit_logs', ['action' => 'leave.document_verified']);
    }

    #[TestWith([false])]
    #[TestWith([true])]
    public function test_approval_notification_attaches_the_current_signed_pdf_bytes_from_the_employee_download(bool $replace): void
    {
        $employee = $this->attendee();
        $supervisor = $this->assign($employee);
        $leave = $this->submit($employee, LeaveType::factory()->create());
        $this->enroll($supervisor);
        config(['mail.default' => 'smtp', 'mail.mailers.smtp.transport' => 'array']);
        Queue::fake([SendEssentialEmail::class]);
        $original = self::TEST_PDF;
        $replacement = "%PDF-1.4\n% revised signed form\n%%EOF";
        $pdf = $replace ? $replacement : $original;
        $this->mock(LeavePdfRenderer::class, function (MockInterface $mock) use ($supervisor, $replace, $original, $replacement): void {
            $mock->shouldReceive('render')->times($replace ? 2 : 1)->withArgs(function (string $html) use ($supervisor): bool {
                $signature = SupervisorSignature::activeFor($supervisor)->sole();
                $signatureImage = base64_encode(app(SupervisorSignatureService::class)->bytes($signature));
                $this->assertStringContainsString('data:image/png;base64,'.$signatureImage.'" alt="Supervisor signature"', $html);
                $this->assertStringContainsString(e($supervisor->name), $html);
                $this->assertStringContainsString('Scan to Verify', $html);

                return true;
            })->andReturn($original, $replacement);
        });

        $this->postJson(route('supervisor.leave.decide', $leave), $this->approval())->assertRedirect();
        $delivery = EssentialEmail::sole();
        $first = $leave->approvalDocument()->sole();
        $this->assertSame($first->id, $delivery->leave_approval_document_id);
        $this->assertTrue(Storage::disk('leave')->exists($first->storage_path));
        Queue::assertPushed(SendEssentialEmail::class, fn (SendEssentialEmail $job): bool => $job->emailId === $delivery->id && $job->afterCommit);
        $transport = Mail::mailer('smtp')->getSymfonyTransport();
        $this->assertCount(0, $transport->messages());

        if ($replace) {
            $this->post(route('leave.verify.replace', $first), [
                'reason' => 'Replace the official PDF for the approved request.',
                'confirmation' => 1, 'current_password' => 'password', 'signature_version' => 1,
            ])->assertRedirect()->assertSessionHasNoErrors();
        }
        $current = $leave->approvalDocument()->sole();
        $job = new SendEssentialEmail($delivery->id);
        app()->call([$job, 'handle']);
        app()->call([$job, 'handle']);

        $mail = $transport->messages()->sole()->getOriginalMessage();
        $this->assertCount(1, $mail->getTo());
        $this->assertSame($employee->work_email, $mail->getTo()[0]->getAddress());
        $this->assertStringContainsString('LIMCOMA leave application approved', $mail->getSubject());
        $this->assertStringContainsString('electronic signature is attached as a PDF', $mail->getHtmlBody());
        $this->assertCount(1, $mail->getAttachments());
        $attachment = $mail->getAttachments()[0];
        $this->assertSame('application/pdf', $attachment->getMediaType().'/'.$attachment->getMediaSubtype());
        $this->assertSame('LIMCOMA_Approved_Leave_'.$current->document_reference.'.pdf', $attachment->getFilename());
        $download = $this->actingAs($employee->user)->get(route('employee.leave.approved-form', $leave))->assertOk()->assertContent($pdf);
        $this->assertSame($download->getContent(), $attachment->getBody());
        $delivery->refresh();
        $this->assertTrue($delivery->attachment_included);
        $this->assertSame('submitted', $delivery->status);
        $this->assertSame($current->document_reference, $delivery->document_reference);
    }

    public function test_oversize_signed_pdf_remains_downloadable_and_is_rejected_before_queueing(): void
    {
        $employee = $this->attendee();
        $supervisor = $this->assign($employee);
        $leave = $this->submit($employee, LeaveType::factory()->create());
        $this->enroll($supervisor);
        config([
            'mail.default' => 'smtp', 'mail.mailers.smtp.transport' => 'array',
            'mail.essential.max_message_bytes' => 1024,
        ]);
        Queue::fake([SendEssentialEmail::class]);
        $this->mock(LeavePdfRenderer::class, fn (MockInterface $mock) => $mock->shouldReceive('render')->once()->andReturn(self::TEST_PDF));

        $this->postJson(route('supervisor.leave.decide', $leave), $this->approval())->assertRedirect();

        Queue::assertNotPushed(SendEssentialEmail::class);
        $delivery = EssentialEmail::sole();
        $this->assertSame('failed', $delivery->status);
        $this->assertSame('signed_pdf_above_message_limit', $delivery->failure_reason);
        $this->assertSame('approved', $leave->fresh()->status);
        $this->actingAs($employee->user)->get(route('employee.leave.approved-form', $leave))->assertOk()->assertContent(self::TEST_PDF);
    }

    #[TestWith(['missing_file', 'retrying', 'signed_pdf_unavailable'])]
    #[TestWith(['oversize', 'failed', 'signed_pdf_above_message_limit'])]
    #[TestWith(['foreign_document', 'failed', 'signed_approval_not_verified'])]
    #[TestWith(['changed_recipient', 'skipped', 'recipient_changed_or_token_expired'])]
    public function test_invalid_approval_attachment_or_recipient_never_sends_email(string $scenario, string $status, string $reason): void
    {
        $employee = $this->attendee();
        $supervisor = $this->assign($employee);
        $leave = $this->submit($employee, LeaveType::factory()->create());
        $this->enroll($supervisor);
        config(['mail.default' => 'smtp', 'mail.mailers.smtp.transport' => 'array']);
        Queue::fake([SendEssentialEmail::class]);
        $this->mock(LeavePdfRenderer::class, fn (MockInterface $mock) => $mock->shouldReceive('render')->once()->andReturn(self::TEST_PDF));
        $this->postJson(route('supervisor.leave.decide', $leave), $this->approval())->assertRedirect();
        $delivery = EssentialEmail::sole();
        $document = $leave->approvalDocument()->sole();

        match ($scenario) {
            'missing_file' => Storage::disk('leave')->delete($document->storage_path),
            'oversize' => config(['mail.essential.max_message_bytes' => 1024]),
            'foreign_document' => $delivery->update(['leave_approval_document_id' => LeaveApprovalDocument::factory()->create()->id]),
            'changed_recipient' => $employee->user->update(['email' => 'changed-owner@example.test']),
        };

        try {
            app()->call([new SendEssentialEmail($delivery->id), 'handle']);
        } catch (RuntimeException $exception) {
            $this->assertSame('missing_file', $scenario);
            $this->assertSame($reason, $exception->getMessage());
        }

        $this->assertCount(0, Mail::mailer('smtp')->getSymfonyTransport()->messages());
        $this->assertSame('approved', $leave->fresh()->status);
        $this->assertSame($status, $delivery->fresh()->status);
        $this->assertSame($reason, $delivery->fresh()->failure_reason);
        $this->assertFalse($delivery->fresh()->attachment_included);
    }

    public function test_pdf_failure_rolls_back_the_decision_balance_and_document(): void
    {
        $employee = $this->attendee();
        $supervisor = $this->assign($employee);
        $type = LeaveType::factory()->create(['affects_balance' => true]);
        $leave = $this->submit($employee, $type);
        $balance = EmployeeLeaveBalance::factory()->create([
            'employee_id' => $employee->id, 'leave_type_id' => $type->id,
            'policy_period' => $leave->start_date->format('Y'), 'available_days' => 5,
        ]);
        $this->enroll($supervisor);
        config(['mail.default' => 'smtp', 'mail.mailers.smtp.transport' => 'array']);
        Queue::fake([SendEssentialEmail::class]);
        $this->mock(LeavePdfRenderer::class, fn (MockInterface $mock) => $mock->shouldReceive('render')->once()->andThrow(new RuntimeException('Test renderer failure')));

        $this->postJson(route('supervisor.leave.decide', $leave), $this->approval())
            ->assertUnprocessable()->assertJsonValidationErrors('decision');
        $this->assertSame('pending_supervisor_approval', $leave->fresh()->status);
        $this->assertSame('5.00', $balance->fresh()->available_days);
        $this->assertDatabaseCount('leave_approval_documents', 0);
        $this->assertDatabaseCount('leave_balance_transactions', 0);
        $this->assertSame([], Storage::disk('leave')->allFiles('leave-approval-documents'));
        $this->assertDatabaseEmpty('essential_emails');
        Queue::assertNotPushed(SendEssentialEmail::class);
    }

    public function test_rejection_requires_remarks_and_leaves_both_decision_boxes_unsigned(): void
    {
        $employee = $this->attendee();
        $supervisor = $this->assign($employee);
        $leave = $this->submit($employee, LeaveType::factory()->create(['name' => 'Sick Leave']));
        $this->enroll($supervisor);

        $url = route('supervisor.leave.decide', $leave);
        $this->postJson($url, ['decision' => 'rejected', 'confirmation' => 1])->assertUnprocessable()->assertJsonValidationErrors('remarks');
        $this->postJson($url, ['decision' => 'rejected', 'confirmation' => 1, 'remarks' => 'The request does not meet the leave policy.'])->assertRedirect();
        $this->assertSame('rejected', $leave->fresh()->status);
        $this->assertDatabaseCount('leave_approval_documents', 0);
        $this->actingAs($employee->user)->get(route('employee.leave.form', $leave))->assertOk()
            ->assertSee('Leave Application — Rejected')
            ->assertSee('aria-label="Unselected"></span> AUTHORIZED LEAVE', false)
            ->assertSee('aria-label="Unselected"></span> UNAUTHORIZED LEAVE', false)
            ->assertSee('class="form-watermark" src="'.asset('LIMCOMA.png').'"', false)
            ->assertDontSee('alt="Supervisor signature"', false);
    }

    private function assign(Employee $employee): User
    {
        $supervisor = User::factory()->create(['role_id' => Role::SUPERVISOR, 'supervisor_department_id' => $employee->department_id]);
        SupervisorAssignment::factory()->create(['employee_id' => $employee->id, 'supervisor_user_id' => $supervisor->id]);

        return $supervisor;
    }

    private function application(LeaveType $type, array $overrides = []): array
    {
        $date = now()->addWeek()->toDateString();

        return array_replace([
            'leave_type_id' => $type->id, 'start_date' => $date, 'end_date' => $date,
            'requested_duration' => 1, 'reason' => 'Submitted for a personal appointment.',
        ], $overrides);
    }

    private function submit(Employee $employee, LeaveType $type, array $overrides = []): LeaveRequest
    {
        $this->actingAs($employee->user)->post(route('employee.leave.store'), $this->application($type, $overrides))
            ->assertRedirect()->assertSessionHasNoErrors();

        return LeaveRequest::sole();
    }

    private function approval(array $overrides = []): array
    {
        return array_replace([
            'decision' => 'approved', 'current_password' => 'password', 'signature_version' => 1,
            'confirmation' => 1, 'chargeable_days' => 1,
        ], $overrides);
    }

    private function enroll(User $supervisor): TestResponse
    {
        return $this->actingAs($supervisor)->post(route('supervisor.signature.store'), [
            'current_password' => 'password',
            'signature' => UploadedFile::fake()->image('signature.png', 160, 45),
        ])->assertRedirect(route('supervisor.signature.show'))->assertSessionHasNoErrors();
    }
}
