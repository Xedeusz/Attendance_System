<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Employee;
use App\Models\LeavePolicy;
use App\Models\LeaveType;
use App\Models\SpecialLeaveEligibilityRequest;
use App\Services\EditVersion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\Support\Phase3Data;
use Tests\TestCase;

class EmployeeSexLeaveVisibilityTest extends TestCase
{
    use Phase3Data, RefreshDatabase;

    #[TestWith(['male'])]
    #[TestWith(['female'])]
    public function test_hr_can_create_employee_with_supported_sex(string $sex): void
    {
        $this->actingAs($this->hr());

        $this->post(route('hr.employees.store'), $this->employeeData(['sex' => $sex]))->assertRedirect()->assertSessionHasNoErrors();

        $employee = Employee::sole();
        $this->assertSame($sex, $employee->sex);
        $this->get(route('hr.employees.show', $employee))->assertOk()->assertSee(ucfirst($sex));
    }

    public function test_employee_creation_requires_supported_sex_value(): void
    {
        $this->actingAs($this->hr());

        $this->post(route('hr.employees.store'), $this->employeeData(['sex' => null]))->assertSessionHasErrors(['sex' => 'Please select the employee\'s sex.']);
        $this->post(route('hr.employees.store'), $this->employeeData(['sex' => 'unsupported']))->assertSessionHasErrors(['sex' => 'Please select Male or Female.']);

        $this->assertDatabaseCount('employees', 0);
    }

    public function test_only_hr_can_change_sex_and_change_is_audited_without_deleting_history(): void
    {
        $hr = $this->hr();
        $this->actingAs($hr);
        $data = $this->employeeData(['sex' => 'female']);
        $employee = $this->registerEmployee($data);
        $maternity = $this->eventType('MATERNITY', 'Maternity Leave', 105, 'calendar_days');
        $eligibility = SpecialLeaveEligibilityRequest::factory()->create(['employee_id' => $employee->id, 'leave_type_id' => $maternity->id, 'status' => 'pending_hr_review']);

        $unauthorizedEmployee = Employee::factory()->create();
        $this->actingAs($unauthorizedEmployee->user)
            ->put(route('hr.employees.update', $employee), [...$data, 'sex' => 'male', '_edit_version' => EditVersion::token($employee)])
            ->assertForbidden();
        $this->assertSame('female', $employee->fresh()->sex);

        $this->actingAs($hr)
            ->put(route('hr.employees.update', $employee), [...$data, 'sex' => 'male', '_edit_version' => EditVersion::token($employee->fresh())])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertSame('male', $employee->fresh()->sex);
        $this->assertModelExists($eligibility->fresh());
        $audit = AuditLog::where('action', 'employee.sex_changed')->latest('id')->firstOrFail();
        $this->assertSame('female', $audit->metadata['previous_sex']);
        $this->assertSame('male', $audit->metadata['sex']);
        $this->assertContains($eligibility->id, $audit->metadata['eligibility_request_ids_requiring_review']);
    }

    public function test_leave_pages_show_sex_applicable_special_type_and_ordinary_types(): void
    {
        LeaveType::factory()->create(['code' => 'VACATION', 'name' => 'Vacation Leave']);
        LeaveType::factory()->create(['code' => 'SICK', 'name' => 'Sick Leave']);
        LeaveType::factory()->create(['code' => 'BIRTHDAY', 'name' => 'Birthday Leave']);
        $maternity = $this->eventType('MATERNITY', 'Maternity Leave', 105, 'calendar_days');
        $paternity = $this->eventType('PATERNITY', 'Paternity Leave', 7, 'working_days');

        $female = Employee::factory()->create(['sex' => 'female']);
        $this->actingAs($female->user)->get(route('employee.leave.create'))
            ->assertOk()->assertSee('Vacation Leave')->assertSee('Sick Leave')->assertSee('Birthday Leave')
            ->assertSee('Maternity Leave')->assertDontSee('Paternity Leave');
        $this->get(route('employee.leave.index'))->assertOk()->assertSee('Maternity Leave')->assertSee('Eligibility verification required')->assertDontSee('Paternity Leave');

        $male = Employee::factory()->create(['sex' => 'male']);
        $this->actingAs($male->user)->get(route('employee.leave.create'))->assertOk()->assertSee('Paternity Leave')->assertDontSee('Maternity Leave');
        $this->get(route('employee.leave.eligibility.create'))->assertOk()->assertSee('Paternity Leave')->assertDontSee('Maternity Leave');

        $legacy = Employee::factory()->create(['sex' => null]);
        $this->actingAs($legacy->user)->get(route('employee.leave.eligibility.create'))
            ->assertOk()->assertSee('HR must complete your employee Sex record')
            ->assertDontSee('value="'.$maternity->id.'"', false)->assertDontSee('value="'.$paternity->id.'"', false);
    }

    public function test_backend_rejects_tampered_special_leave_and_verification_submissions(): void
    {
        $female = Employee::factory()->create(['sex' => 'female']);
        $paternity = $this->eventType('PATERNITY', 'Paternity Leave', 7, 'working_days');
        $date = now(config('attendance.timezone'))->addWeek()->toDateString();

        $this->actingAs($female->user)->post(route('employee.leave.store'), [
            'leave_type_id' => $paternity->id, 'start_date' => $date, 'end_date' => $date, 'requested_duration' => 1,
            'reason' => 'Attempted browser payload bypass for a hidden leave type.',
        ])->assertSessionHasErrors(['leave_type_id' => 'This leave type is not available for your current eligibility record. Please contact HR for verification.']);
        $this->post(route('employee.leave.eligibility.store'), [
            'leave_type_id' => $paternity->id, 'event_category' => 'paternity_qualifying_event', 'actual_event_date' => $date,
            'additional_information' => 'Attempted browser payload bypass for eligibility verification.', 'employee_declaration' => 1,
        ])->assertSessionHasErrors(['leave_type_id']);

        $this->assertDatabaseCount('leave_requests', 0);
        $this->assertDatabaseCount('special_leave_eligibility_requests', 0);
    }

    public function test_existing_authorized_exception_remains_visible_after_sex_change(): void
    {
        $employee = Employee::factory()->create(['sex' => 'male']);
        $maternity = $this->eventType('MATERNITY', 'Maternity Leave', 105, 'calendar_days');
        SpecialLeaveEligibilityRequest::factory()->create(['employee_id' => $employee->id, 'leave_type_id' => $maternity->id, 'status' => 'verified']);

        $this->actingAs($employee->user)->get(route('employee.leave.create'))->assertOk()->assertSee('Maternity Leave');
    }

    private function eventType(string $code, string $name, int $days, string $dayBasis): LeaveType
    {
        $type = LeaveType::factory()->create(['code' => $code, 'name' => $name, 'affects_balance' => true]);
        LeavePolicy::factory()->create(['leave_type_id' => $type->id, 'allocation_basis' => 'event', 'default_days' => $days, 'day_basis' => $dayBasis]);

        return $type;
    }
}
