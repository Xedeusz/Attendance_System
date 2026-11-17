<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\Role;
use App\Models\SupervisorAssignment;
use App\Models\User;
use App\Services\ProfilePhotoService;
use App\Services\WorkspaceSync;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\Support\AttendanceData;
use Tests\TestCase;

class EmployeeProfileAccessTest extends TestCase
{
    use AttendanceData, RefreshDatabase;

    private function assign(Employee $employee): User
    {
        $supervisor = User::factory()->create(['role_id' => Role::SUPERVISOR, 'supervisor_department_id' => $employee->department_id]);
        SupervisorAssignment::factory()->create(['employee_id' => $employee->id, 'supervisor_user_id' => $supervisor->id]);

        return $supervisor;
    }

    public function test_hr_views_real_profiles_across_branches_and_existing_edit_actions(): void
    {
        $employees = [$this->attendee(), $this->attendee()];
        $this->actingAs($this->reviewer());
        $this->get('/hr/employees')->assertOk()->assertSee('View Profile');
        foreach ($employees as $employee) {
            $this->get(route('hr.employees.show', $employee))->assertOk()->assertSee($employee->full_name)->assertSee($employee->work_email)->assertSee($employee->currentAssignment->branch->branch_name)->assertSee(route('hr.employees.edit', $employee))->assertDontSee('Change Photo');
        }
    }

    public function test_supervisor_sees_only_current_assigned_work_information_and_no_edit_controls(): void
    {
        $employee = $this->attendee();
        $employee->update(['contact_number' => '09171234567']);
        $supervisor = $this->assign($employee);
        $unassigned = Employee::factory()->create(['department_id' => $employee->department_id, 'first_name' => 'UnassignedPrivate']);
        $this->actingAs($supervisor);
        $url = route('supervisor.leave.employees.profile', $employee);
        $this->get('/supervisor/leave')->assertOk()->assertSee($url)->assertDontSee($unassigned->full_name);
        $this->get($url)->assertOk()->assertSee('Read-only Employee Profile')->assertSee($employee->work_email)->assertSee($employee->department->department_name)->assertDontSee('09171234567')->assertDontSee('Contact number')->assertDontSee('Change Photo')->assertDontSee('Remove Photo')->assertDontSee('Edit employee')->assertDontSee('Deactivate account')->assertDontSee('Attendance records');
        $this->get(route('supervisor.leave.employees.profile', $unassigned))->assertForbidden();
        $this->get(route('supervisor.leave.employees.profile', $this->attendee()))->assertForbidden();
        $this->get('/hr/employees')->assertForbidden();
        $this->patch(route('hr.employees.update', $employee), ['first_name' => 'Tampered'])->assertForbidden();
        $this->postJson('/employee/profile/photo', ['employee_id' => $employee->id])->assertForbidden();
        $this->deleteJson('/employee/profile/photo', ['employee_id' => $employee->id])->assertForbidden();
    }

    public function test_expired_future_revoked_and_department_mismatched_assignments_cannot_open_profiles_or_photos(): void
    {
        Storage::fake('local');
        $employee = $this->attendee();
        $photoUser = app(ProfilePhotoService::class)->update($employee->user, $this->cameraImage());
        $supervisor = $this->assign($employee);
        $assignment = SupervisorAssignment::where('employee_id', $employee->id)->sole();
        $original = $assignment->getAttributes();
        $this->actingAs($supervisor);
        $photoUrl = $photoUser->avatarData()['url'];
        foreach ([['effective_until' => now()->subDay()->toDateString()], ['effective_from' => now()->addDay()->toDateString()], ['is_active' => false], ['revoked_at' => now()]] as $change) {
            $assignment->forceFill(array_replace($original, $change))->save();
            $this->get(route('supervisor.leave.employees.profile', $employee))->assertForbidden();
            $this->get($photoUrl)->assertForbidden();
        }
        $assignment->forceFill($original)->save();
        $employee->update(['department_id' => Department::factory()->create()->id]);
        $this->get(route('supervisor.leave.employees.profile', $employee))->assertForbidden();
        $this->get($photoUrl)->assertForbidden();
    }

    public function test_private_photos_follow_view_permission_and_change_versions_without_cross_user_avatar_state(): void
    {
        Storage::fake('local');
        $employee = $this->attendee();
        $owner = app(ProfilePhotoService::class)->update($employee->user, $this->cameraImage());
        $supervisor = $this->assign($employee);
        $this->actingAs($supervisor);
        $photo = $owner->avatarData()['url'];
        $this->get($photo)->assertOk()->assertHeader('Content-Type', 'image/jpeg');
        $this->get(route('supervisor.leave.employees.profile', $employee))->assertSee($photo, false);
        $this->actingAs($this->reviewer())->get($photo)->assertOk();
        $this->actingAs($this->attendee()->user)->get($photo)->assertForbidden();
        $owner = app(ProfilePhotoService::class)->update($owner, $this->cameraImage(80));
        $this->actingAs($supervisor)->get($photo)->assertNotFound();
        $this->get($owner->avatarData()['url'])->assertOk();
        app(ProfilePhotoService::class)->update($owner, null);
        $this->get(route('supervisor.leave.employees.profile', $employee))->assertOk()->assertSee($owner->avatarData()['initials'])->assertDontSee('/profile/photo/employees/');
    }

    public function test_employee_keeps_own_profile_actions_and_cannot_use_admin_profile_routes(): void
    {
        $employee = $this->attendee();
        $other = $this->attendee();
        $this->get(route('supervisor.leave.employees.profile', $employee))->assertRedirect('/login');
        $this->actingAs($employee->user)->get('/employee/profile')->assertOk()->assertSee('Change Photo')->assertSee('Save contact number');
        $this->get(route('hr.employees.show', $other))->assertForbidden();
        $this->get(route('supervisor.leave.employees.profile', $other))->assertForbidden();
    }

    public function test_profile_updates_invalidate_only_authorized_workspaces_and_pages_work_without_reverb(): void
    {
        $employee = $this->attendee();
        $supervisor = $this->assign($employee);
        $other = User::factory()->create(['role_id' => Role::SUPERVISOR]);
        app(WorkspaceSync::class)->record('profile.photo_updated', $employee->user);
        $this->assertDatabaseHas('workspace_revisions', ['user_id' => $supervisor->id, 'topic' => 'leave']);
        $this->assertDatabaseMissing('workspace_revisions', ['user_id' => $other->id]);
        $before = DB::table('workspace_revisions')->where('user_id', $supervisor->id)->where('topic', 'leave')->value('revision');
        $employee->update(['department_id' => Department::factory()->create()->id]);
        app(WorkspaceSync::class)->record('employee.updated', $employee);
        $this->assertNotSame($before, DB::table('workspace_revisions')->where('user_id', $supervisor->id)->where('topic', 'leave')->value('revision'));
        $this->actingAs($supervisor)->get(route('supervisor.leave.employees.profile', $employee))->assertForbidden();
        config(['broadcasting.default' => 'null']);
        $this->actingAs($this->reviewer())->get(route('hr.employees.show', $employee))->assertOk();
    }

    public function test_leave_profile_links_preserve_the_application_and_historical_records_do_not_grant_profile_access(): void
    {
        $employee = $this->attendee();
        $supervisor = $this->assign($employee);
        $leave = LeaveRequest::factory()->create(['employee_id' => $employee->id, 'assigned_supervisor_user_id' => $supervisor->id, 'status' => 'pending_supervisor_approval']);
        $url = route('supervisor.leave.employees.profile', [$employee, 'leave' => $leave->id]);
        $this->actingAs($supervisor)->get(route('supervisor.leave.show', $leave))->assertOk()->assertSee($url, false)->assertSee('target="_blank"', false);
        $this->get($url)->assertOk()->assertSee(route('supervisor.leave.show', $leave))->assertSee('Back to leave application');
        $wrongLeave = LeaveRequest::factory()->create();
        $this->get(route('supervisor.leave.employees.profile', [$employee, 'leave' => $wrongLeave->id]))->assertForbidden();
        SupervisorAssignment::where('employee_id', $employee->id)->update(['is_active' => false, 'revoked_at' => now()]);
        $this->get($url)->assertForbidden();
        $this->assertDatabaseHas('leave_requests', ['id' => $leave->id, 'status' => 'pending_supervisor_approval']);
    }
}
