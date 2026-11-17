<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\LeaveRequest;
use App\Models\Role;
use App\Models\SupervisorAssignment;
use App\Models\User;
use App\Services\EditVersion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\Support\AttendanceData;
use Tests\TestCase;

class SupervisorDepartmentTest extends TestCase
{
    use AttendanceData, RefreshDatabase;

    private function manager(): void
    {
        $this->actingAs($this->reviewer())->withSession(['auth.password_confirmed_at' => now()->timestamp]);
    }

    private function supervisor(): User
    {
        return User::factory()->create(['role_id' => Role::SUPERVISOR]);
    }

    private function link(User $user, int $department): TestResponse
    {
        return $this->post('/hr/supervisors/'.$user->id.'/department', ['supervisor_department_id' => $department, '_edit_version' => EditVersion::token($user)]);
    }

    public function test_hr_can_link_an_unlinked_account_and_all_views_use_the_direct_department(): void
    {
        $this->manager();
        $sup = $this->supervisor();
        $dept = Department::factory()->create();
        $this->link($sup, $dept->id)->assertSessionHasNoErrors()->assertRedirect();
        $this->assertSame($dept->id, $sup->fresh()->supervisor_department_id);
        $this->assertNull($sup->employee);
        $this->assertDatabaseHas('audit_logs', ['action' => 'supervisor.department_changed', 'subject_id' => $sup->id]);
        foreach (['/hr/supervisors', '/hr/supervisors/'.$sup->id, '/hr/supervisors/'.$sup->id.'/edit'] as $path) {
            $this->get($path)->assertOk()->assertSee($dept->department_name)->assertDontSee('No linked employee profile');
        }
        $this->actingAs($sup->fresh())->get('/supervisor/leave')->assertOk()->assertSee($dept->department_name);
    }

    public function test_only_hr_can_link_existing_active_departments_to_supervisors(): void
    {
        $sup = $this->supervisor();
        $dept = Department::factory()->create();
        foreach ([$sup, $this->attendee()->user] as $actor) {
            $this->actingAs($actor)->withSession(['auth.password_confirmed_at' => now()->timestamp]);
            $this->link($sup, $dept->id)->assertForbidden();
        }
        $this->manager();
        $this->link($sup, 999999)->assertSessionHasErrors('supervisor_department_id');
        $dept->update(['status' => 'inactive']);
        $this->link($sup, $dept->id)->assertSessionHasErrors('supervisor_department_id');
        $this->assertNull($sup->fresh()->supervisor_department_id);
    }

    public function test_matching_legacy_assignments_survive_linking_and_conflicting_changes_are_blocked(): void
    {
        $this->manager();
        $employee = $this->attendee();
        $sup = $this->supervisor();
        $assignment = SupervisorAssignment::factory()->create(['employee_id' => $employee->id, 'supervisor_user_id' => $sup->id]);
        $this->assertFalse(SupervisorAssignment::current()->whereKey($assignment->id)->exists());
        $this->link($sup, $employee->department_id)->assertSessionHasNoErrors();
        $this->assertTrue(SupervisorAssignment::current()->whereKey($assignment->id)->exists());
        $this->link($sup, Department::factory()->create()->id)->assertSessionHasErrors('supervisor_department_id');
        $this->assertTrue($assignment->fresh()->is_active);
        $this->assertDatabaseCount('supervisor_assignments', 1);
    }

    public function test_department_is_not_permission_and_employee_transfers_immediately_remove_leave_scope(): void
    {
        $this->manager();
        $employee = $this->attendee();
        $sup = $this->supervisor();
        $this->link($sup, $employee->department_id)->assertSessionHasNoErrors();
        $leave = LeaveRequest::factory()->create(['employee_id' => $employee->id, 'assigned_supervisor_user_id' => $sup->id, 'status' => 'pending_supervisor_approval']);
        $this->actingAs($sup->fresh())->get('/supervisor/leave/'.$leave->id)->assertForbidden();
        SupervisorAssignment::factory()->create(['employee_id' => $employee->id, 'supervisor_user_id' => $sup->id]);
        $this->get('/supervisor/leave/'.$leave->id)->assertOk();
        $employee->update(['department_id' => Department::factory()->create()->id]);
        $this->get('/supervisor/leave/'.$leave->id)->assertForbidden();
        $this->assertDatabaseCount('supervisor_assignments', 1);
    }

    public function test_employee_dropdown_and_server_enforce_department_eligibility(): void
    {
        $this->manager();
        $employee = $this->attendee();
        $other = $this->attendee();
        $sup = $this->supervisor();
        $this->post('/hr/supervisors/'.$sup->id.'/assignments', ['employee_id' => $employee->id, 'effective_from' => now()->toDateString()])->assertSessionHasErrors('employee_id');
        $this->link($sup, $employee->department_id)->assertSessionHasNoErrors();
        $this->get('/hr/supervisors/'.$sup->id)->assertViewHas('employees', fn ($rows) => $rows->contains('id', $employee->id) && ! $rows->contains('id', $other->id));
        $this->post('/hr/supervisors/'.$sup->id.'/assignments', ['employee_id' => $other->id, 'effective_from' => now()->toDateString()])->assertSessionHasErrors('employee_id');
        $this->post('/hr/supervisors/'.$sup->id.'/assignments', ['employee_id' => $employee->id, 'effective_from' => now()->toDateString()])->assertSessionHasNoErrors();
        $this->assertDatabaseCount('supervisor_assignments', 1);
    }

    public function test_edit_form_uses_the_same_link_and_stale_department_saves_are_rejected(): void
    {
        $this->manager();
        $sup = $this->supervisor();
        $dept = Department::factory()->create();
        $version = EditVersion::token($sup);
        $this->put('/hr/supervisors/'.$sup->id, ['first_name' => 'Synthetic', 'last_name' => 'Manager', 'email' => $sup->email, 'account_status' => 'active', 'supervisor_department_id' => $dept->id, '_edit_version' => $version])->assertSessionHasNoErrors();
        $this->post('/hr/supervisors/'.$sup->id.'/department', ['supervisor_department_id' => Department::factory()->create()->id, '_edit_version' => $version])->assertSessionHasErrors('_edit_version');
        $this->assertSame($dept->id,$sup->fresh()->supervisor_department_id);
    }
}
