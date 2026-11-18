<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\AttendanceData;
use Tests\TestCase;

class EmployeeDashboardTest extends TestCase
{
    use AttendanceData, RefreshDatabase;

    public function test_identification_and_empty_attendance_use_only_the_signed_in_employee(): void
    {
        $employee = $this->attendee();
        $employee->update(['first_name' => 'Alexandria', 'middle_name' => 'Marisol', 'last_name' => 'Montemayor Fernández', 'position_title' => 'Workplace Support Specialist']);
        $employee->department->update(['department_name' => 'Support Services']);
        $employee->currentAssignment->branch->update(['branch_name' => 'Assigned Office']);
        $otherEmployee = $this->attendee();
        $this->punch($this->capture($otherEmployee))->assertOk();

        $this->actingAs($employee->user)->get(route('employee.dashboard'))
            ->assertOk()
            ->assertSeeText(['Employee identification', 'LIMCOMA Multipurpose Cooperative', 'Alexandria Marisol Montemayor Fernández', $employee->employee_number, 'Assigned Office', 'Support Services', 'Workplace Support Specialist', 'Employment status'])
            ->assertSee('LIMCOMA-LOGO.png')
            ->assertSeeText('AM')
            ->assertSeeText(['My Attendance', 'Not Clocked In', 'No attendance recorded for today. Open attendance to start your workday.'])
            ->assertSee(route('employee.attendance'), false)
            ->assertSee(route('employee.attendance.history'), false)
            ->assertDontSeeText($otherEmployee->employee_number)
            ->assertViewHas('records', fn ($records) => $records->isEmpty());
    }

    public function test_today_shows_saved_time_in_and_time_out_in_manila_time(): void
    {
        $this->travelTo(now()->setDate(2026, 11, 18)->setTime(16, 15));
        $employee = $this->attendee();
        $this->punch($this->capture($employee))->assertOk();

        $this->get(route('employee.dashboard'))->assertOk()
            ->assertSeeText(['Thu, Nov 19, 2026', 'Asia/Manila', 'Clocked In', '12:15 AM'])
            ->assertSee('datetime="2026-11-19T00:15:00+08:00"', false);

        $this->travel(9)->hours();
        $this->punch($this->capture($employee, 'time_out'), [], 180)->assertOk();

        $this->get(route('employee.dashboard'))->assertOk()
            ->assertSeeText(['Clocked Out', '12:15 AM', '9:15 AM']);

        $this->travel(1)->day();

        $this->get(route('employee.dashboard'))->assertOk()
            ->assertSeeText('Not Clocked In')
            ->assertDontSeeText('9:15 AM')
            ->assertSeeText('Completed');
    }

    public function test_an_overnight_open_session_stays_visible_on_the_next_workday(): void
    {
        $this->travelTo(now()->setDate(2026, 11, 18)->setTime(15, 30));
        $employee = $this->attendee();
        $this->punch($this->capture($employee))->assertOk();
        $this->travel(2)->hours();

        $this->get(route('employee.dashboard'))->assertOk()
            ->assertSeeText(['Clocked In', '11:30 PM'])
            ->assertSeeTextInOrder(['Open session from', 'Nov 18, 2026']);
    }

    public function test_identity_card_omits_unavailable_department_and_position(): void
    {
        $employee = $this->attendee();
        $employee->setRelation('department', null);
        $employee->position_title = '';
        $user = $employee->user;

        $this->actingAs($user)->blade('<x-employee-identity-card :employee="$employee" :user="$user" />', [
            'employee' => $employee,
            'user' => $user,
        ])->assertSee($employee->full_name)
            ->assertDontSee('Department')
            ->assertDontSee('Position');
    }
}
