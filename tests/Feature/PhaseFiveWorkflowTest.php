<?php

namespace Tests\Feature;

use App\Models\AttendanceCorrectionRequest;
use App\Models\AttendanceEvent;
use App\Models\AttendanceRecord;
use App\Models\Employee;
use App\Models\EmployeeLeaveBalance;
use App\Models\LeaveBalanceTransaction;
use App\Models\LeaveRequest;
use App\Models\LeaveReview;
use App\Models\LeaveType;
use App\Models\Role;
use App\Models\SupervisorAssignment;
use App\Models\User;
use App\Services\LeavePdfRenderer;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Tests\Support\AttendanceData;
use Tests\TestCase;

class PhaseFiveWorkflowTest extends TestCase
{
    use AttendanceData,RefreshDatabase;

    private function supervisor(): User
    {
        return User::factory()->create(['role_id' => Role::SUPERVISOR]);
    }

    private function application(LeaveType $type, array $overrides = []): array
    {
        return array_replace(['leave_type_id' => $type->id, 'start_date' => now()->addWeek()->toDateString(), 'end_date' => now()->addWeek()->addDays(3)->toDateString(), 'requested_duration' => 3, 'reason' => 'A private synthetic request for personal leave.'], $overrides);
    }

    public function test_application_dates_use_the_manila_calendar_and_preserve_historical_access(): void
    {
        $this->travelTo(Carbon::parse('2026-11-18 16:05:00', 'UTC'));
        $employee = $this->attendee();
        $type = LeaveType::factory()->create(['requires_document' => false, 'affects_balance' => false]);
        $this->actingAs($employee->user)->get(route('employee.leave.create'))->assertOk()
            ->assertSee('min="2026-11-19"', false);

        foreach (['2026-11-18', '2026-10-01'] as $past) {
            $this->postJson(route('employee.leave.store'), $this->application($type, [
                'start_date' => $past, 'end_date' => $past,
            ]))->assertUnprocessable()->assertJsonValidationErrors(['start_date', 'end_date']);
        }
        $this->postJson(route('employee.leave.store'), $this->application($type, [
            'start_date' => '2026-11-21', 'end_date' => '2026-11-20',
        ]))->assertUnprocessable()->assertJsonValidationErrors('end_date');
        $this->assertDatabaseEmpty('leave_requests');

        foreach (['2026-11-19', '2026-11-20', '2026-11-25'] as $date) {
            $leave = $this->apply($employee, $type, ['start_date' => $date, 'end_date' => $date, 'requested_duration' => 1]);
            $this->assertSame('1.00', $leave->requested_duration);
        }
        $this->travel(1)->month();
        foreach (['approved', 'rejected'] as $status) {
            $leave->update(['status' => $status]);
            $this->get(route('employee.leave.show', $leave))->assertOk()->assertSee('Nov 25, 2026');
        }
    }

    private function assign(Employee $employee, User $supervisor): SupervisorAssignment
    {
        $supervisor->forceFill(['supervisor_department_id' => $employee->department_id])->save();

        return SupervisorAssignment::factory()->create(['employee_id' => $employee->id, 'supervisor_user_id' => $supervisor->id]);
    }

    private function apply(Employee $employee, LeaveType $type, array $overrides = []): LeaveRequest
    {
        $this->actingAs($employee->user)->post('/employee/leave', $this->application($type, $overrides))->assertRedirect()->assertSessionHasNoErrors();

        return LeaveRequest::latest('id')->firstOrFail();
    }

    private function decision(LeaveRequest $leave, array $overrides = []): TestResponse
    {
        return $this->postJson('/supervisor/leave/'.$leave->id.'/decision', array_replace(['decision' => 'approved', 'remarks' => 'Reviewed the actual work dates and supporting information.', 'confirmation' => 1, 'current_password' => 'password', 'signature_version' => 1], $overrides));
    }

    private function enroll(User $supervisor): void
    {
        Storage::fake('leave');
        $this->mock(LeavePdfRenderer::class)->shouldReceive('render')->once()->andReturn("%PDF-1.4\n%%EOF");
        $this->actingAs($supervisor)->post(route('supervisor.signature.store'), [
            'current_password' => 'password',
            'signature' => UploadedFile::fake()->image('signature.png', 160, 45),
        ])->assertRedirect()->assertSessionHasNoErrors();
    }

    public function test_unassigned_application_is_saved_and_hr_assignment_routes_it_without_resetting_submission(): void
    {
        $employee = $this->attendee();
        $hr = $this->reviewer();
        $type = LeaveType::factory()->create();
        $leave = $this->apply($employee, $type);
        $submitted = $leave->submitted_at->toIso8601String();
        $this->assertSame('awaiting_supervisor_assignment', $leave->status);
        $this->assertNull($leave->assigned_supervisor_user_id);
        $this->assertTrue($hr->notifications()->where('data->record_id', $leave->id)->exists());
        $supervisor = $this->supervisor();
        $supervisor->forceFill(['supervisor_department_id' => $employee->department_id])->save();
        $this->actingAs($hr)->withSession(['auth.password_confirmed_at' => time()])->post('/hr/supervisors/'.$supervisor->id.'/assignments', ['employee_id' => $employee->id, 'effective_from' => now()->subDay()->toDateString(), 'confirmation' => 1])->assertRedirect()->assertSessionHasNoErrors();
        $leave->refresh();
        $this->assertSame('pending_supervisor_approval', $leave->status);
        $this->assertSame($supervisor->id, $leave->assigned_supervisor_user_id);
        $this->assertSame($submitted, $leave->submitted_at->toIso8601String());
        $this->actingAs($supervisor)->get('/supervisor/leave')->assertOk()->assertSee($employee->employee_number);
        $this->get('/supervisor/leave/'.$leave->id)->assertOk()->assertSee('Final Supervisor decision');
        $this->enroll($supervisor);
        $this->decision($leave)->assertRedirect();
        $this->assertSame('approved', $leave->fresh()->status);
        $this->assertNotNull($leave->fresh()->finalized_at);
        $this->actingAs($hr)->get('/hr/leave/'.$leave->id)->assertOk()->assertDontSee('name="decision"', false);
        $this->decision($leave)->assertForbidden();
    }

    public function test_final_approval_debits_the_configured_balance_once_and_duplicate_decision_conflicts(): void
    {
        $employee = $this->attendee();
        $type = LeaveType::factory()->create(['affects_balance' => true, 'allows_half_day' => true]);
        $supervisor = $this->supervisor();
        $supervisor->forceFill(['supervisor_department_id' => $employee->department_id])->save();
        $this->assign($employee, $supervisor);
        $hr = $this->reviewer();
        $this->actingAs($hr)->withSession(['auth.password_confirmed_at' => time()])->post('/hr/leave/balances', ['employee_id' => $employee->id, 'leave_type_id' => $type->id, 'policy_period' => now()->format('Y'), 'amount' => 7, 'reason' => 'Approved synthetic opening balance for this test.', 'confirmation' => 1])->assertRedirect()->assertSessionHasNoErrors();
        $leave = $this->apply($employee, $type);
        $balance = EmployeeLeaveBalance::sole();
        $this->assertSame('7.00', $balance->available_days);
        $this->actingAs($supervisor);
        $this->enroll($supervisor);
        $this->decision($leave, ['chargeable_days' => 2.5])->assertRedirect();
        $this->assertSame('4.50', $balance->fresh()->available_days);
        $this->assertSame('2.50', $leave->fresh()->charged_duration);
        $this->decision($leave, ['chargeable_days' => 2.5])->assertConflict();
        $this->assertSame(1, LeaveBalanceTransaction::where('leave_request_id', $leave->id)->count());
        $this->assertSame(1, $leave->reviews()->whereIn('action', ['approved', 'rejected'])->count());
        $this->assertTrue($employee->user->notifications()->where('data->title', 'Your leave application was approved and signed. Remaining balance: 4.5 days.')->exists());
        $this->actingAs($employee->user)->post('/employee/leave/'.$leave->id.'/cancel', ['reason' => 'Please cancel this approved application.', 'confirmation' => 1])->assertConflict();
    }

    public function test_required_balance_and_confirmed_duration_are_checked_before_approval(): void
    {
        $employee = $this->attendee();
        $type = LeaveType::factory()->create(['affects_balance' => true]);
        $supervisor = $this->supervisor();
        $supervisor->forceFill(['supervisor_department_id' => $employee->department_id])->save();
        $this->assign($employee, $supervisor);
        $balance = EmployeeLeaveBalance::factory()->create(['employee_id' => $employee->id, 'leave_type_id' => $type->id, 'available_days' => 7]);
        $leave = $this->apply($employee, $type);
        $this->actingAs($supervisor);
        $this->decision($leave)->assertUnprocessable()->assertJsonValidationErrors('chargeable_days');
        $balance->update(['available_days' => 1]);
        $this->decision($leave, ['chargeable_days' => 3])->assertUnprocessable();
        $this->assertSame('pending_supervisor_approval', $leave->fresh()->status);
        $this->assertSame('1.00', $balance->fresh()->available_days);
        $this->decision($leave, ['decision' => 'rejected', 'remarks' => 'short'])->assertUnprocessable();
        $this->decision($leave, ['decision' => 'rejected', 'remarks' => 'The requested dates cannot be accommodated.'])->assertRedirect();
        $this->assertSame('rejected', $leave->fresh()->status);
        $this->assertDatabaseCount('leave_balance_transactions', 0);
    }

    public function test_leave_dates_overlap_half_day_policy_and_forged_identity_are_validated(): void
    {
        $employee = $this->attendee();
        $type = LeaveType::factory()->create();
        $this->actingAs($employee->user);
        $this->postJson('/employee/leave', $this->application($type, ['end_date' => now()->toDateString()]))->assertUnprocessable()->assertJsonValidationErrors('end_date');
        $this->postJson('/employee/leave', $this->application($type, ['requested_duration' => 0.5, 'end_date' => now()->addWeek()->toDateString(), 'half_day' => 'am']))->assertUnprocessable()->assertJsonValidationErrors('half_day');
        $this->postJson('/employee/leave', $this->application($type, ['employee_id' => 999, 'status' => 'approved']))->assertUnprocessable()->assertJsonValidationErrors(['employee_id', 'status']);
        $this->apply($employee, $type);
        $this->postJson('/employee/leave', $this->application($type))->assertUnprocessable()->assertJsonValidationErrors('start_date');
        $this->assertDatabaseCount('leave_requests', 1);
    }

    public function test_document_content_is_validated_encrypted_and_protected_by_current_scope(): void
    {
        Storage::fake('leave');
        $employee = $this->attendee();
        $type = LeaveType::factory()->create(['requires_document' => true]);
        $supervisor = $this->supervisor();
        $supervisor->forceFill(['supervisor_department_id' => $employee->department_id])->save();
        $assignment = $this->assign($employee, $supervisor);
        $this->actingAs($employee->user);
        $this->postJson('/employee/leave', $this->application($type))->assertUnprocessable()->assertJsonValidationErrors('supporting_document');
        $this->postJson('/employee/leave', $this->application($type, ['supporting_document' => UploadedFile::fake()->createWithContent('bad.pdf', '<script>not a PDF</script>')]))->assertUnprocessable();
        $pdf = "%PDF-1.4\n1 0 obj\n<< /Type /Catalog >>\nendobj\n%%EOF";
        $leave = $this->apply($employee, $type, ['supporting_document' => UploadedFile::fake()->createWithContent('synthetic.pdf', $pdf)]);
        $document = $leave->documents()->sole();
        $this->assertNotSame($pdf, Storage::disk('leave')->get($document->storage_path));
        $this->assertSame($pdf, Crypt::decryptString(Storage::disk('leave')->get($document->storage_path)));
        $this->assertNotSame($leave->reason, DB::table('leave_requests')->where('id', $leave->id)->value('reason'));
        $this->get('/employee/leave/documents/'.$document->id)->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->actingAs($this->reviewer())->get('/hr/leave/documents/'.$document->id)->assertOk();
        foreach (['employee' => $employee->user, 'hr' => $this->reviewer()] as $scope => $viewer) {
            $url = route($scope.'.leave.document', [$document, 'preview' => 1]);
            $this->actingAs($viewer)->get(route($scope.'.leave.show', $leave))->assertOk()
                ->assertSee('View document')->assertSee($url)->assertSee('document-preview-name');
            $this->get($url)->assertOk()->assertHeader('Content-Disposition', 'inline; filename=synthetic.pdf')->assertStreamedContent($pdf);
            $this->get(route($scope.'.leave.document', $document))->assertDownload('synthetic.pdf');
        }
        $other = Employee::factory()->create()->user;
        $this->actingAs($other)->get(route('employee.leave.document', [$document, 'preview' => 1]))->assertForbidden();
        $this->get(route('hr.leave.document', [$document, 'preview' => 1]))->assertForbidden();

        $download = route('supervisor.leave.document', $document);
        $preview = route('supervisor.leave.document', [$document, 'preview' => 1]);
        $this->actingAs($supervisor)->get($download)->assertDownload('synthetic.pdf');
        $this->get($preview)->assertOk()->assertHeader('Content-Type', 'application/pdf')
            ->assertHeader('Content-Disposition', 'inline; filename=synthetic.pdf')->assertStreamedContent($pdf);
        $this->get(route('supervisor.leave.show', $leave))->assertOk()->assertSee('View document')->assertSee($preview);
        foreach (['png' => 'image/png', 'jpg' => 'image/jpeg'] as $extension => $mime) {
            $image = UploadedFile::fake()->image('sample.'.$extension, 20, 12)->getContent();
            Storage::disk('leave')->put($document->storage_path, Crypt::encryptString($image));
            $document->update(['mime_type' => $mime]);
            $this->get($preview)->assertOk()->assertHeader('Content-Type', $mime)->assertStreamedContent($image);
        }
        Storage::disk('leave')->put($document->storage_path, Crypt::encryptString('<html>Unsupported</html>'));
        $this->get($preview)->assertStatus(415);
        $this->actingAs($this->supervisor())->get($preview)->assertForbidden();
        $this->actingAs(Employee::factory()->create()->user)->get($preview)->assertForbidden();
        $this->actingAs($this->supervisor())->get('/supervisor/leave/documents/'.$document->id)->assertForbidden();
        $this->actingAs(Employee::factory()->create()->user)->get('/employee/leave/documents/'.$document->id)->assertForbidden();
        $assignment->update(['is_active' => false, 'revoked_at' => now()]);
        $this->actingAs($supervisor)->get('/supervisor/leave/documents/'.$document->id)->assertForbidden();
    }

    public function test_pending_cancellation_preserves_history_and_notifies_the_supervisor_without_a_debit(): void
    {
        $employee = $this->attendee();
        $supervisor = $this->supervisor();
        $supervisor->forceFill(['supervisor_department_id' => $employee->department_id])->save();
        $this->assign($employee, $supervisor);
        $leave = $this->apply($employee, LeaveType::factory()->create());
        $this->post('/employee/leave/'.$leave->id.'/cancel', ['reason' => 'The requested leave is no longer needed.', 'confirmation' => 1])->assertRedirect();
        $this->assertSame('cancelled', $leave->fresh()->status);
        $this->assertTrue($leave->reviews()->where('action', 'cancelled')->exists());
        $this->assertDatabaseCount('leave_balance_transactions', 0);
        $this->assertTrue($supervisor->notifications()->where('data->title', 'An assigned leave application was cancelled.')->exists());
    }

    public function test_manual_checkout_keeps_original_evidence_and_releases_the_open_session_guard(): void
    {
        $employee = $this->attendee();
        $this->punch($this->capture($employee))->assertOk();
        $record = AttendanceRecord::sole();
        $event = AttendanceEvent::sole();
        $original = $event->getRawOriginal();
        $timeIn = $record->time_in_at->toIso8601String();
        $this->travel(8)->hours();
        $out = now()->copy()->timezone(config('attendance.timezone'))->format('Y-m-d\TH:i');
        $this->post('/employee/attendance/corrections', ['attendance_record_id' => $record->id, 'correction_type' => 'forgotten_time_out', 'requested_date' => $record->work_date->toDateString(), 'requested_time_out_at' => $out, 'reason' => 'Forgot to record the checkout at the office.'])->assertRedirect()->assertSessionHasNoErrors();
        $correction = AttendanceCorrectionRequest::sole();
        $this->actingAs($this->reviewer())->get('/hr/attendance/corrections/'.$correction->id)->assertOk();
        $this->post('/hr/attendance/corrections/'.$correction->id.'/decision', ['decision' => 'approved', 'remarks' => 'Verified the manual checkout with the employee.', 'confirmation' => 1])->assertRedirect()->assertSessionHasNoErrors();
        $record->refresh();
        $this->assertNull($record->time_out_at);
        $this->assertNotNull($record->effective_time_out_at);
        $this->assertSame($timeIn, $record->time_in_at->toIso8601String());
        $this->assertSame($original, $event->fresh()->getRawOriginal());
        $this->assertSame('location_validated', $record->verification_status);
        $this->postJson('/hr/attendance/corrections/'.$correction->id.'/decision', ['decision' => 'rejected', 'remarks' => 'A repeated decision must be rejected.', 'confirmation' => 1])->assertConflict();
        $this->actingAs($employee->user)->get('/employee/attendance/history')->assertOk()->assertSee('Manual correction');
        $this->get(route('attendance.show', $record))->assertOk()->assertSee('Effective attendance');
        $this->travel(2)->minutes();
        $this->punch($this->capture($employee), [], 180)->assertOk();
        $this->assertDatabaseCount('attendance_records', 2);
    }

    public function test_missing_attendance_approval_creates_a_manual_entry_without_fabricated_camera_or_gps(): void
    {
        $employee = $this->attendee();
        $this->actingAs($employee->user);
        $day = now(config('attendance.timezone'))->subDay()->toDateString();
        $data = ['correction_type' => 'missing_record', 'requested_date' => $day, 'requested_time_in_at' => $day.'T08:00', 'requested_time_out_at' => $day.'T17:00', 'reason' => 'No attendance was captured because the device failed.'];
        $this->postJson('/employee/attendance/corrections', array_replace($data, ['requested_time_out_at' => null]))->assertUnprocessable()->assertJsonValidationErrors('requested_time_out_at');
        $this->post('/employee/attendance/corrections', $data)->assertRedirect()->assertSessionHasNoErrors();
        $correction = AttendanceCorrectionRequest::sole();
        $this->postJson('/employee/attendance/corrections', $data)->assertUnprocessable();
        $this->actingAs($this->reviewer())->post('/hr/attendance/corrections/'.$correction->id.'/decision', ['decision' => 'approved', 'remarks' => 'Confirmed these manual times using independent records.', 'confirmation' => 1])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseCount('attendance_records', 0);
        $this->assertDatabaseCount('attendance_events', 0);
        $this->actingAs($employee->user)->get('/employee/attendance/history')->assertOk()->assertSee('Approved manual entries')->assertSee('Camera and GPS were not verified');
        $this->postJson('/employee/attendance/corrections', $data)->assertUnprocessable();
    }

    public function test_a_genuine_checkout_after_correction_submission_requires_a_new_hr_review(): void
    {
        $employee = $this->attendee();
        $this->punch($this->capture($employee))->assertOk();
        $record = AttendanceRecord::sole();
        $this->travel(8)->hours();
        $this->post('/employee/attendance/corrections', ['attendance_record_id' => $record->id, 'correction_type' => 'forgotten_time_out', 'requested_date' => $record->work_date->toDateString(), 'requested_time_out_at' => now(config('attendance.timezone'))->format('Y-m-d\TH:i'), 'reason' => 'Proposed checkout before the genuine event was recorded.'])->assertRedirect()->assertSessionHasNoErrors();
        $correction = AttendanceCorrectionRequest::sole();
        $this->travel(2)->minutes();
        $this->punch($this->capture($employee, 'time_out'), [], 180)->assertOk();
        $this->assertNotNull($record->fresh()->time_out_at);
        $this->actingAs($this->reviewer())->postJson('/hr/attendance/corrections/'.$correction->id.'/decision', ['decision' => 'approved', 'remarks' => 'This earlier request is now stale after a genuine checkout.', 'confirmation' => 1])->assertConflict();
        $this->assertSame('pending', $correction->fresh()->status);
        $this->assertNull($record->fresh()->effective_time_out_at);
    }

    public function test_half_day_requires_one_date_and_charge_cannot_exceed_the_requested_duration(): void
    {
        $employee = $this->attendee();
        $type = LeaveType::factory()->create(['allows_half_day' => true, 'affects_balance' => true]);
        $supervisor = $this->supervisor();
        $supervisor->forceFill(['supervisor_department_id' => $employee->department_id])->save();
        $this->assign($employee, $supervisor);
        EmployeeLeaveBalance::factory()->create(['employee_id' => $employee->id, 'leave_type_id' => $type->id, 'available_days' => 2]);
        $day = now()->addWeek()->toDateString();
        $this->actingAs($employee->user)->postJson('/employee/leave', $this->application($type, ['requested_duration' => 0.5, 'half_day' => 'am']))->assertUnprocessable();
        $leave = $this->apply($employee, $type, ['end_date' => $day, 'requested_duration' => 0.5, 'half_day' => 'pm']);
        $this->actingAs($supervisor);
        $this->decision($leave, ['chargeable_days' => 1])->assertUnprocessable();
        $this->enroll($supervisor);
        $this->decision($leave, ['chargeable_days' => 0.5])->assertRedirect();
        $this->assertSame('1.50', EmployeeLeaveBalance::sole()->available_days);
    }

    public function test_changed_leave_policy_and_inactive_requester_prevent_final_approval(): void
    {
        $employee = $this->attendee();
        $type = LeaveType::factory()->create();
        $supervisor = $this->supervisor();
        $supervisor->forceFill(['supervisor_department_id' => $employee->department_id])->save();
        $this->assign($employee, $supervisor);
        $leave = $this->apply($employee, $type);
        $type->update(['affects_balance' => true]);
        $this->actingAs($supervisor);
        $this->decision($leave, ['chargeable_days' => 1])->assertUnprocessable()->assertJsonValidationErrors('decision');
        $type->update(['affects_balance' => false]);
        $employee->user->forceFill(['account_status' => 'deactivated'])->save();
        $this->decision($leave)->assertUnprocessable();
        $this->assertSame('pending_supervisor_approval', $leave->fresh()->status);
    }

    public function test_configured_document_retention_removes_bytes_and_keeps_the_application_and_metadata(): void
    {
        Storage::fake('leave');
        config(['leave.document_retention_days' => 1]);
        $employee = $this->attendee();
        $type = LeaveType::factory()->create(['requires_document' => true]);
        $leave = $this->apply($employee, $type, ['supporting_document' => UploadedFile::fake()->createWithContent('synthetic.pdf', "%PDF-1.4\n1 0 obj\n<< /Type /Catalog >>\nendobj\n%%EOF")]);
        $document = $leave->documents()->sole();
        $this->assertNotNull($document->purge_after);
        Storage::disk('leave')->assertExists($document->storage_path);
        $this->travel(2)->days();
        $this->artisan('leave:purge-documents')->assertSuccessful();
        Storage::disk('leave')->assertMissing($document->storage_path);
        $this->assertNotNull($document->fresh()->purged_at);
        $this->assertDatabaseHas('leave_requests', ['id' => $leave->id]);
        $this->get('/employee/leave/documents/'.$document->id)->assertNotFound();
    }

    public function test_upload_size_type_and_missing_confirmation_cannot_finalize_a_request(): void
    {
        $employee = $this->attendee();
        $type = LeaveType::factory()->create();
        $supervisor = $this->supervisor();
        $supervisor->forceFill(['supervisor_department_id' => $employee->department_id])->save();
        $this->assign($employee, $supervisor);
        $this->actingAs($employee->user);
        $this->postJson('/employee/leave', $this->application($type, ['supporting_document' => UploadedFile::fake()->create('large.pdf', 6000, 'application/pdf')]))->assertUnprocessable()->assertJsonValidationErrors('supporting_document');
        $leave = $this->apply($employee, $type);
        $this->actingAs($supervisor);
        $this->decision($leave, ['confirmation' => 0])->assertUnprocessable();
        $this->assertSame('pending_supervisor_approval', $leave->fresh()->status);
    }

    public function test_rejected_or_cancelled_correction_never_changes_effective_attendance(): void
    {
        $employee = $this->attendee();
        $correction = AttendanceCorrectionRequest::factory()->create(['employee_id' => $employee->id]);
        $this->actingAs($this->reviewer())->postJson('/hr/attendance/corrections/'.$correction->id.'/decision', ['decision' => 'rejected', 'remarks' => 'Insufficient independent information to authorize the change.', 'confirmation' => 1])->assertRedirect();
        $this->assertSame('rejected', $correction->fresh()->status);
        $this->assertDatabaseCount('attendance_records', 0);
        $this->assertNull($correction->fresh()->pending_key);
        $pending = AttendanceCorrectionRequest::factory()->create(['employee_id' => $employee->id]);
        $this->actingAs($employee->user)->post('/employee/attendance/corrections/'.$pending->id.'/cancel', ['confirmation' => 1])->assertRedirect();
        $this->assertSame('cancelled', $pending->fresh()->status);
        $this->assertNull($pending->fresh()->pending_key);
    }

    public function test_final_review_uniqueness_is_enforced_by_the_database_as_well_as_the_workflow(): void
    {
        $leave = LeaveRequest::factory()->create();
        LeaveReview::factory()->create(['leave_request_id' => $leave->id, 'action' => 'approved']);
        $this->expectException(QueryException::class);
        LeaveReview::factory()->create(['leave_request_id' => $leave->id, 'action' => 'rejected']);
    }

    public function test_workflow_notifications_are_owned_and_coexist_with_attendance_updates(): void
    {
        $employee = $this->attendee();
        $leave = $this->apply($employee, LeaveType::factory()->create());
        $notice = $employee->user->notifications()->sole();
        $this->get('/notifications')->assertOk()->assertSee('Your leave application is awaiting Supervisor assignment.')->assertSee('Attendance review updates');
        $this->post('/notifications/'.$notice->id.'/read')->assertRedirect();
        $this->assertNotNull($notice->fresh()->read_at);
        $this->actingAs(Employee::factory()->create()->user)->post('/notifications/'.$notice->id.'/read')->assertNotFound();
    }
}
