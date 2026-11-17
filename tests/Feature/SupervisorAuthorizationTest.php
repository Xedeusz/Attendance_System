<?php

namespace Tests\Feature;

use App\Models\AccountInvitation;
use App\Models\AttendanceCorrectionRequest;
use App\Models\AttendanceEvent;
use App\Models\AttendanceRecord;
use App\Models\Employee;
use App\Models\EmployeeLeaveBalance;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\Role;
use App\Models\SupervisorAssignment;
use App\Models\User;
use App\Notifications\WorkflowNotice;
use App\Services\InvitationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\AttendanceData;
use Tests\TestCase;

class SupervisorAuthorizationTest extends TestCase
{
    use AttendanceData,RefreshDatabase;

    private function supervisor(): User
    {
        return User::factory()->create(['role_id' => Role::SUPERVISOR]);
    }

    private function pending(Employee $employee, User $supervisor): LeaveRequest
    {
        $supervisor->forceFill(['supervisor_department_id' => $employee->department_id])->save();
        SupervisorAssignment::factory()->create(['employee_id' => $employee->id, 'supervisor_user_id' => $supervisor->id]);

        return LeaveRequest::factory()->create(['employee_id' => $employee->id, 'assigned_supervisor_user_id' => $supervisor->id, 'status' => 'pending_supervisor_approval']);
    }

    public static function forbiddenPages(): array
    {
        return array_map(fn ($path) => [$path], ['/hr/dashboard', '/hr/employees', '/hr/branches', '/hr/departments', '/hr/hr-accounts', '/hr/supervisors', '/hr/leave', '/hr/leave/types', '/hr/leave/balances', '/hr/attendance', '/hr/attendance/corrections', '/hr/audit-logs', '/hr/local-mail', '/reports', '/employees', '/branches', '/attendance', '/attendance-history', '/employee/dashboard', '/employee/profile', '/employee/attendance', '/employee/attendance/history', '/employee/attendance/corrections', '/employee/attendance/corrections/create', '/notifications']);
    }

    #[DataProvider('forbiddenPages')]
    public function test_supervisor_cannot_open_unrelated_modules_even_by_direct_url(string $path): void
    {
        $this->actingAs($this->supervisor())->get($path)->assertForbidden();
    }

    public function test_supervisor_login_lands_in_leave_and_its_menu_contains_only_leave_and_personal_utilities(): void
    {
        $supervisor = $this->supervisor();
        $this->post('/login', ['email' => $supervisor->email, 'password' => 'password'])->assertRedirect(route('supervisor.leave.index'));
        $this->assertAuthenticatedAs($supervisor);
        $this->get('/dashboard')->assertRedirect(route('supervisor.leave.index'));
        $this->get('/supervisor/leave')->assertOk()->assertSee('Leave Overview')->assertSee('Pending Leave Requests')->assertSee('Leave History')->assertDontSee('title="Employee Management"', false)->assertDontSee('title="Attendance Monitoring"', false)->assertDontSee('title="Time In / Time Out"', false);
        $this->get('/settings')->assertOk()->assertDontSee('HR Account Management');
    }

    public function test_employee_and_hr_cannot_make_supervisor_decisions_and_unrelated_users_cannot_read_requests(): void
    {
        $employee = $this->attendee();
        $supervisor = $this->supervisor();
        $leave = $this->pending($employee, $supervisor);
        $other = $this->attendee();
        $decision = ['decision' => 'approved', 'remarks' => 'This approval must not be accepted.', 'confirmation' => 1];
        $this->actingAs($employee->user)->postJson('/supervisor/leave/'.$leave->id.'/decision', $decision)->assertForbidden();
        $this->actingAs($this->reviewer())->postJson('/supervisor/leave/'.$leave->id.'/decision', $decision)->assertForbidden();
        $this->actingAs($other->user)->get('/employee/leave/'.$leave->id)->assertForbidden();
        $this->actingAs($this->supervisor())->get('/supervisor/leave/'.$leave->id)->assertForbidden();
        $this->postJson('/supervisor/leave/'.$leave->id.'/decision', $decision)->assertForbidden();
        $this->assertSame('pending_supervisor_approval', $leave->fresh()->status);
    }

    public function test_assignment_replacement_revokes_access_immediately_and_preserves_routing_history(): void
    {
        $hr = $this->reviewer();
        $first = $this->supervisor();
        $second = $this->supervisor();
        // The replacement reviewer must meet the same department prerequisite.
        $employee = $this->attendee();
        $second->forceFill(['supervisor_department_id' => $employee->department_id])->save();
        $leave = $this->pending($employee, $first);
        $assignment = SupervisorAssignment::where('employee_id', $employee->id)->sole();
        $this->actingAs($hr)->withSession(['auth.password_confirmed_at' => time()]);
        $data = ['employee_id' => $employee->id, 'effective_from' => now(config('attendance.timezone'))->toDateString()];
        $this->postJson('/hr/supervisors/'.$second->id.'/assignments', $data)->assertUnprocessable();
        $this->post('/hr/supervisors/'.$second->id.'/assignments', [...$data, 'replace_existing' => 1])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertFalse($assignment->fresh()->is_active);
        $this->assertNotNull($assignment->fresh()->revoked_at);
        $this->assertSame($second->id, $leave->fresh()->assigned_supervisor_user_id);
        $this->assertTrue($leave->reviews()->where('action', 'supervisor_reassigned')->exists());
        $this->actingAs($first)->get('/supervisor/leave/'.$leave->id)->assertForbidden();
        $this->actingAs($second)->get('/supervisor/leave/'.$leave->id)->assertOk();
        $this->get('/supervisor/leave?q=does-not-match')->assertOk()->assertViewHas('leaves', fn ($rows) => $rows->isEmpty());
    }

    public function test_assignment_expiry_inactive_accounts_and_revocation_are_rechecked_on_review(): void
    {
        $employee = $this->attendee();
        $supervisor = $this->supervisor();
        $leave = $this->pending($employee, $supervisor);
        $assignment = SupervisorAssignment::where('employee_id', $employee->id)->sole();
        $assignment->update(['effective_until' => now(config('attendance.timezone'))->subDay()->toDateString()]);
        $this->actingAs($supervisor)->postJson('/supervisor/leave/'.$leave->id.'/decision', ['decision' => 'approved', 'remarks' => 'Review attempted after assignment expiration.', 'confirmation' => 1])->assertForbidden();
        $this->artisan('leave:refresh-routing')->assertSuccessful();
        $this->assertSame('awaiting_supervisor_assignment', $leave->fresh()->status);
        $assignment->update(['effective_until' => null]);
        $supervisor->forceFill(['account_status' => 'deactivated'])->save();
        $this->actingAs($supervisor)->get('/supervisor/leave')->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_future_assignment_replacement_preserves_current_scope_until_the_effective_date(): void
    {
        $employee = $this->attendee();
        $first = $this->supervisor();
        $second = $this->supervisor();
        // The replacement reviewer must meet the same department prerequisite.
        $second->forceFill(['supervisor_department_id' => $employee->department_id])->save();
        $leave = $this->pending($employee, $first);
        $assignment = SupervisorAssignment::where('employee_id', $employee->id)->sole();
        $starts = now(config('attendance.timezone'))->addDays(2)->toDateString();
        $this->actingAs($this->reviewer())->withSession(['auth.password_confirmed_at' => time()])->post('/hr/supervisors/'.$second->id.'/assignments', ['employee_id' => $employee->id, 'effective_from' => $starts, 'replace_existing' => 1])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertTrue($assignment->fresh()->is_active);
        $this->assertSame(now(config('attendance.timezone'))->addDay()->toDateString(), $assignment->fresh()->effective_until->toDateString());
        $this->assertSame($first->id, $leave->fresh()->assigned_supervisor_user_id);
        $this->actingAs($first)->get('/supervisor/leave/'.$leave->id)->assertOk();
        $this->travel(2)->days();
        $this->artisan('leave:refresh-routing')->assertSuccessful();
        $this->assertSame($second->id, $leave->fresh()->assigned_supervisor_user_id);
        $this->actingAs($first)->get('/supervisor/leave/'.$leave->id)->assertForbidden();
        $this->actingAs($second)->get('/supervisor/leave/'.$leave->id)->assertOk();
    }

    public function test_supervisor_cannot_self_review_and_own_request_waits_for_a_different_supervisor(): void
    {
        $employee = $this->attendee();
        $supervisor = $employee->user;
        $supervisor->forceFill(['role_id' => Role::SUPERVISOR])->save();
        $supervisor->forceFill(['supervisor_department_id' => $employee->department_id])->save();
        SupervisorAssignment::factory()->create(['employee_id' => $employee->id, 'supervisor_user_id' => $supervisor->id]);
        $type = LeaveType::factory()->create();
        $this->actingAs($supervisor)->post('/employee/leave', ['leave_type_id' => $type->id, 'start_date' => now()->addDays(10)->toDateString(), 'end_date' => now()->addDays(10)->toDateString(), 'requested_duration' => 1, 'reason' => 'Own application must require another reviewer.'])->assertRedirect()->assertSessionHasNoErrors();
        $leave = LeaveRequest::sole();
        $this->assertSame('awaiting_supervisor_assignment', $leave->status);
        $this->assertFalse(Gate::forUser($supervisor)->allows('review', $leave));
        $this->get('/employee/leave/'.$leave->id)->assertOk();
        $this->postJson('/supervisor/leave/'.$leave->id.'/decision', ['decision' => 'approved', 'remarks' => 'Attempted self approval must be prevented.', 'confirmation' => 1])->assertForbidden();
    }

    public function test_hr_promotion_reuses_identity_and_revokes_old_sessions_and_hr_can_manage_all_pages(): void
    {
        $employee = $this->attendee();
        $this->punch($this->capture($employee))->assertOk();
        $recordId = AttendanceRecord::sole()->id;
        $userId = $employee->user_id;
        DB::table('sessions')->insert(['id' => 'synthetic-old-session', 'user_id' => $userId, 'payload' => '', 'last_activity' => time()]);
        $this->actingAs($this->reviewer())->withSession(['auth.password_confirmed_at' => time()])->post('/hr/supervisors', ['employee_id' => $employee->id, 'email' => $employee->user->email, 'account_status' => 'active'])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame($userId, $employee->fresh()->user_id);
        $this->assertSame(Role::SUPERVISOR, $employee->user->fresh()->role_id);
        $this->assertSame(2, $employee->user->fresh()->session_version);
        $this->assertDatabaseMissing('sessions', ['id' => 'synthetic-old-session']);
        $this->assertDatabaseHas('attendance_records', ['id' => $recordId, 'employee_id' => $employee->id]);
        LeaveType::factory()->create();
        EmployeeLeaveBalance::factory()->create(['employee_id' => $employee->id]);
        AttendanceCorrectionRequest::factory()->create(['employee_id' => $employee->id]);
        foreach (['/hr/supervisors', '/hr/supervisors/create', '/hr/supervisors/'.$userId, '/hr/supervisors/'.$userId.'/edit', '/hr/leave', '/hr/leave/types', '/hr/leave/balances', '/hr/attendance/corrections'] as $path) {
            $this->get($path)->assertOk();
        }
    }

    public function test_only_hr_can_provision_supervisors_and_new_accounts_need_single_use_invitation_activation(): void
    {
        $data = ['first_name' => 'Synthetic', 'last_name' => 'Supervisor', 'email' => 'new-supervisor@example.test', 'account_status' => 'pending_activation'];
        $this->actingAs($this->attendee()->user)->post('/hr/supervisors', $data)->assertForbidden();
        $this->actingAs($this->supervisor())->post('/hr/supervisors', $data)->assertForbidden();
        $hr = $this->reviewer();
        $this->actingAs($hr)->withSession(['auth.password_confirmed_at' => time()]);
        $this->post('/hr/supervisors', [...$data, 'account_status' => 'active'])->assertSessionHasErrors('account_status');
        $this->post('/hr/supervisors', $data)->assertRedirect()->assertSessionHasNoErrors();
        $user = User::where('email', $data['email'])->sole();
        $this->assertSame(Role::SUPERVISOR, $user->role_id);
        $this->assertFalse($user->canAuthenticate());
        $this->assertTrue(AccountInvitation::where('user_id', $user->id)->exists());
        $issued = app(InvitationService::class)->issue($user);
        $this->assertNotSame($issued['token'], $issued['invitation']->token_hash);
        app(InvitationService::class)->activate($issued['token'], 'Synthet1c-Activated!Password');
        $this->assertTrue($user->fresh()->canAuthenticate());
        $this->assertNotNull($issued['invitation']->fresh()->used_at);
        $this->expectException(ValidationException::class);
        app(InvitationService::class)->activate($issued['token'], 'Another-Synthet1c!Password');
    }

    public function test_supervisor_cannot_view_even_own_attendance_evidence_or_corrections(): void
    {
        $employee = $this->attendee();
        $this->punch($this->capture($employee))->assertOk();
        $record = AttendanceRecord::sole();
        $photo = AttendanceEvent::sole()->photo;
        $correction = AttendanceCorrectionRequest::factory()->create(['employee_id' => $employee->id]);
        $supervisor = $employee->user;
        $supervisor->forceFill(['role_id' => Role::SUPERVISOR])->save();
        $this->actingAs($supervisor);
        $this->get(route('attendance.show', $record))->assertForbidden();
        $this->get(route('attendance.photo', $photo))->assertForbidden();
        $this->get('/employee/attendance/corrections/'.$correction->id)->assertForbidden();
        $this->get('/hr/attendance/corrections/'.$correction->id)->assertForbidden();
        $this->postJson('/hr/attendance/corrections/'.$correction->id.'/decision', ['decision' => 'approved', 'remarks' => 'This review must never be accepted.', 'confirmation' => 1])->assertForbidden();
    }

    public function test_correction_idor_and_self_review_are_denied_and_stale_or_overlapping_changes_are_blocked(): void
    {
        $employee = $this->attendee();
        $other = $this->attendee();
        $correction = AttendanceCorrectionRequest::factory()->create(['employee_id' => $employee->id]);
        $this->actingAs($other->user)->get('/employee/attendance/corrections/'.$correction->id)->assertForbidden();
        $this->postJson('/employee/attendance/corrections', ['attendance_record_id' => 99999, 'correction_type' => 'other', 'requested_date' => now()->subDay()->toDateString(), 'requested_time_in_at' => now()->subDay()->format('Y-m-d\TH:i'), 'reason' => 'Attempting a different attendance identifier.'])->assertNotFound();
        $employee->user->forceFill(['role_id' => Role::HR])->save();
        $this->actingAs($employee->user)->postJson('/hr/attendance/corrections/'.$correction->id.'/decision', ['decision' => 'approved', 'remarks' => 'Attempted HR self review must be denied.', 'confirmation' => 1])->assertForbidden();
        $record = AttendanceRecord::create(['employee_id' => $employee->id, 'branch_id' => $employee->currentAssignment->branch_id, 'work_date' => $correction->requested_date, 'time_in_at' => $correction->requested_time_in_at, 'time_out_at' => $correction->requested_time_out_at, 'session_status' => 'completed', 'verification_status' => 'location_validated', 'review_status' => 'none']);
        $this->actingAs($this->reviewer())->postJson('/hr/attendance/corrections/'.$correction->id.'/decision', ['decision' => 'approved', 'remarks' => 'This overlaps an existing captured attendance.', 'confirmation' => 1])->assertUnprocessable();
        $correction->update(['attendance_record_id' => $record->id, 'record_revision' => 0]);
        $record->forceFill(['correction_version' => 1])->save();
        $this->postJson('/hr/attendance/corrections/'.$correction->id.'/decision', ['decision' => 'approved', 'remarks' => 'This correction is based on a stale record.', 'confirmation' => 1])->assertConflict();
    }

    public function test_supervisor_notices_are_leave_only_and_employee_cannot_manage_types_or_balances(): void
    {
        $supervisor = $this->supervisor();
        $supervisor->notify(new WorkflowNotice('leave', 'Assigned leave needs review.', route('supervisor.leave.index'), 1));
        $supervisor->notify(new WorkflowNotice('correction', 'Private HR correction task.', route('hr.corrections.index'), 1));
        $this->actingAs($supervisor)->get('/supervisor/leave/notifications')->assertOk()->assertSee('Assigned leave needs review.')->assertDontSee('Private HR correction task.');
        $notice = $supervisor->notifications()->where('data->category', 'correction')->sole();
        $this->post('/supervisor/leave/notifications/'.$notice->id.'/read')->assertNotFound();
        $this->actingAs($this->attendee()->user);
        $this->post('/hr/leave/types', [])->assertForbidden();
        $this->post('/hr/leave/balances', [])->assertForbidden();
    }
}
