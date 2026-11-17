<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Department;
use App\Models\Employee;
use App\Services\EditVersion;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\Phase3Data;
use Tests\TestCase;

class BranchDepartmentTest extends TestCase
{
    use Phase3Data, RefreshDatabase;

    public function test_hr_can_create_edit_and_filter_branches_and_departments(): void
    {
        $this->actingAs($this->hr());
        $branch = Branch::factory()->make(['latitude' => 14.5995, 'longitude' => 120.9842, 'geofence_radius_meters' => 150])->toArray();
        $this->post('/hr/branches', $branch)->assertSessionHasNoErrors()->assertRedirect();
        $record = Branch::sole();
        $this->put('/hr/branches/'.$record->id, [...$branch, '_edit_version' => EditVersion::token($record), 'branch_name' => 'Updated QA Branch'])->assertSessionHasNoErrors();
        $this->get('/hr/branches?q=Updated&sort=branch_code&direction=desc')->assertOk()->assertSee('Updated QA Branch');
        $this->post('/hr/departments', ['department_code' => 'QA-DEPT', 'department_name' => 'QA Department', 'status' => 'active'])->assertSessionHasNoErrors();
        $department = Department::sole();
        $this->put('/hr/departments/'.$department->id, ['_edit_version' => EditVersion::token($department), 'department_code' => 'QA-DEPT', 'department_name' => 'Changed QA Department', 'status' => 'active'])->assertSessionHasNoErrors();
        $this->get('/hr/departments?q=Changed')->assertOk()->assertSee('Changed QA Department');
    }

    public function test_duplicate_codes_and_invalid_coordinate_pairs_are_rejected(): void
    {
        $this->actingAs($this->hr());
        $branch = Branch::factory()->create();
        $this->post('/hr/branches', $branch->toArray())->assertSessionHasErrors('branch_code');
        $this->post('/hr/branches', [...$branch->toArray(), 'branch_code' => 'QA-NEW', 'latitude' => 91, 'longitude' => 181, 'geofence_radius_meters' => 0])->assertSessionHasErrors(['latitude', 'longitude', 'geofence_radius_meters']);
        $this->post('/hr/branches', [...$branch->toArray(), 'branch_code' => 'QA-NEW', 'latitude' => 10])->assertSessionHasErrors('longitude');
    }

    public function test_active_assignments_block_branch_and_department_deactivation(): void
    {
        $this->actingAs($this->hr());
        $employee = $this->registerEmployee($data = $this->employeeData());
        $branch = $employee->currentAssignment->branch;
        $department = $employee->department;
        $this->put('/hr/branches/'.$branch->id, [...$branch->toArray(), 'status' => 'inactive', 'confirmation' => 1])->assertSessionHasErrors('status');
        $this->put('/hr/departments/'.$department->id, [...$department->toArray(), 'status' => 'inactive', 'confirmation' => 1])->assertSessionHasErrors('status');
        $branch->update(['status' => 'inactive']);
        $this->post('/hr/employees', [...$data, 'employee_number' => 'QA-SECOND', 'work_email' => 'second@example.test'])->assertSessionHasErrors('branch_id');
    }

    public function test_deactivation_requires_confirmation_and_preserves_records(): void
    {
        $this->actingAs($this->hr());
        $branch = Branch::factory()->create();
        $this->put('/hr/branches/'.$branch->id, [...$branch->toArray(), 'status' => 'inactive'])->assertSessionHasErrors('confirmation');
        $this->put('/hr/branches/'.$branch->id, [...$branch->toArray(), '_edit_version' => EditVersion::token($branch), 'status' => 'inactive', 'confirmation' => 1])->assertSessionHasNoErrors();
        $this->assertSame('inactive', $branch->fresh()->status);
        $this->assertDatabaseCount('branches', 1);
    }

    public function test_database_prevents_two_open_primary_assignments(): void
    {
        $employee = Employee::factory()->create();
        $branch = Branch::factory()->create();
        $employee->assignments()->create(['branch_id' => $branch->id, 'effective_from' => now()]);
        $this->expectException(QueryException::class);
        $employee->assignments()->create(['branch_id' => $branch->id, 'effective_from' => now()]);
    }
}
