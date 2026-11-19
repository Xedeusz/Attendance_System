<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\WorkSchedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\AttendanceData;
use Tests\TestCase;

class EmployeeWorkCalendarTest extends TestCase
{
    use AttendanceData, RefreshDatabase;

    public function test_approved_leave_calendar_events_share_period_status_and_details(): void
    {
        $this->travelTo(now('Asia/Manila')->setDate(2026, 11, 20)->setTime(10, 0));
        $employee = $this->attendee();
        WorkSchedule::factory()->create();
        $this->leave($employee, 'Approved ongoing leave', 'approved', '2026-11-17', '2026-11-20');
        $this->leave($employee, 'Approved upcoming leave', 'approved', '2026-11-25', '2026-11-26');
        $this->leave($employee, 'Approved completed leave', 'approved', '2026-11-10', '2026-11-12');
        $this->leave($employee, 'Pending leave', 'pending_supervisor_approval', '2026-11-25', '2026-11-26');
        $this->leave($employee, 'Rejected leave', 'rejected', '2026-11-10', '2026-11-12');

        $response = $this->actingAs($employee->user)->get(route('employee.work-calendar', ['month' => '2026-11']));

        $response->assertOk()
            ->assertSeeText(['Leave · Upcoming', 'Leave · Ongoing', 'Leave · Completed'])
            ->assertDontSeeText(['Pending leave', 'Rejected leave']);

        $this->get(route('employee.work-calendar', ['month' => '2026-11', 'day' => '2026-11-17', 'details' => 1]))
            ->assertOk()
            ->assertSeeText('Leave period: Ongoing');
        $this->get(route('employee.work-calendar', ['month' => '2026-11', 'day' => '2026-11-20', 'details' => 1]))
            ->assertOk()
            ->assertSeeText(['Approved ongoing leave', 'Approval status: Approved', 'Leave period: Ongoing', 'Number of approved leave days: 4']);

        $this->travelTo(now('Asia/Manila')->setDate(2026, 11, 21)->setTime(10, 0));

        $this->get(route('employee.work-calendar', ['month' => '2026-11']))
            ->assertOk()
            ->assertSeeText('Leave · Completed');
    }

    private function leave(Employee $employee, string $name, string $status, string $start, string $end): LeaveRequest
    {
        return LeaveRequest::factory()->create([
            'employee_id' => $employee->id,
            'status' => $status,
            'start_date' => $start,
            'end_date' => $end,
            'requested_duration' => 1 + (strtotime($end) - strtotime($start)) / 86400,
            'type_snapshot' => ['name' => $name],
        ]);
    }
}
