<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Department;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\Role;
use App\Models\SupervisorAssignment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Pagination\Cursor;
use Tests\TestCase;

class LeaveCalendarTest extends TestCase
{
    use RefreshDatabase;

    private const RANGE = ['from' => '2026-11-16', 'to' => '2026-11-22'];

    public function test_routes_enforce_personal_and_current_explicit_supervisor_access(): void
    {
        $owner = Employee::factory()->create();
        $sameDepartment = Employee::factory()->create(['department_id' => $owner->department_id]);
        $supervisor = $this->assign($owner);
        $assignment = SupervisorAssignment::where('supervisor_user_id', $supervisor->id)->firstOrFail();
        $visible = $this->leave($owner, ['assigned_supervisor_user_id' => $supervisor->id, 'status' => 'pending_supervisor_approval']);
        $unassigned = $this->leave($sameDepartment, ['assigned_supervisor_user_id' => $supervisor->id, 'status' => 'approved']);
        $waiting = $this->leave($owner, ['assigned_supervisor_user_id' => null, 'status' => 'awaiting_supervisor_assignment']);
        $historical = $this->leave($owner, ['assigned_supervisor_user_id' => $supervisor->id, 'status' => 'rejected']);
        $historical->reviews()->create(['reviewer_user_id' => $supervisor->id, 'action' => 'rejected', 'remarks' => 'Requested dates cannot be accommodated.', 'reviewed_at' => now()]);
        $this->getJson(route('employee.leave.calendar.events', self::RANGE))->assertUnauthorized();

        $this->actingAs($owner->user)->get(route('employee.leave.calendar.index'))->assertOk()->assertSee('My Leave Calendar');
        $this->getJson(route('employee.leave.calendar.events', self::RANGE))->assertOk()
            ->assertJsonCount(2, 'events')->assertJsonFragment(['id' => $visible->id, 'title' => $visible->type_snapshot['name']])
            ->assertJsonMissing(['id' => $unassigned->id])->assertJsonPath('staffing', null)->assertDontSee($visible->reason);
        $this->getJson(route('employee.leave.calendar.events', self::RANGE + ['employee_id' => $sameDepartment->id]))->assertUnprocessable();
        $this->getJson(route('employee.leave.calendar.details', $unassigned))->assertNotFound();
        $this->getJson(route('employee.leave.calendar.details', $historical))->assertOk()
            ->assertJsonPath('remarks', 'Requested dates cannot be accommodated.')
            ->assertJsonPath('url', route('employee.leave.show', $historical, false));
        $this->getJson(route('hr.leave.calendar.events', self::RANGE))->assertForbidden();

        $this->actingAs($supervisor)->get(route('supervisor.leave.calendar.index'))->assertOk()->assertSee('Team Leave Calendar')->assertDontSee('Name or employee number');
        $this->getJson(route('supervisor.leave.calendar.events', self::RANGE))->assertOk()->assertJsonCount(1, 'events')
            ->assertJsonPath('events.0.id', $visible->id)->assertJsonMissing(['id' => $waiting->id])->assertJsonCount(1, 'team');
        $this->getJson(route('supervisor.leave.calendar.events', self::RANGE + ['employee_id' => $sameDepartment->id]))->assertOk()->assertJsonCount(0, 'events');
        $this->getJson(route('supervisor.leave.calendar.events', self::RANGE + ['department_id' => $owner->department_id]))->assertUnprocessable();
        $this->getJson(route('supervisor.leave.calendar.details', $visible))->assertOk()->assertJsonPath('url', route('supervisor.leave.show', $visible, false));
        $this->getJson(route('supervisor.leave.calendar.details', $unassigned))->assertNotFound();
        $this->getJson(route('supervisor.leave.calendar.events', self::RANGE + ['status' => 'awaiting_supervisor_assignment']))->assertUnprocessable();
        $this->getJson(route('hr.leave.calendar.events', self::RANGE))->assertForbidden();

        $assignment->update(['effective_until' => now(config('attendance.timezone'))->subDay()->toDateString()]);
        $this->getJson(route('supervisor.leave.calendar.events', self::RANGE))->assertOk()->assertJsonCount(0, 'events')->assertJsonCount(0, 'team');
        $this->getJson(route('supervisor.leave.calendar.details', $visible))->assertNotFound();
        $assignment->update(['effective_until' => null]);
        $supervisor->forceFill(['supervisor_department_id' => Department::factory()->create()->id])->save();
        $this->getJson(route('supervisor.leave.calendar.details', $visible))->assertNotFound();
        $supervisor->forceFill(['supervisor_department_id' => $owner->department_id])->save();
        $assignment->update(['revoked_at' => now()]);
        $this->getJson(route('supervisor.leave.calendar.details', $visible))->assertNotFound();
    }

    public function test_approved_event_titles_use_period_status_and_details_preserve_approval(): void
    {
        $this->travelTo(now('Asia/Manila')->setDate(2026, 11, 19)->setTime(10, 0));
        $employee = Employee::factory()->create();
        $upcoming = $this->leave($employee, ['status' => 'approved', 'start_date' => '2026-11-25', 'end_date' => '2026-11-26']);
        $ongoing = $this->leave($employee, ['status' => 'approved', 'start_date' => '2026-11-18', 'end_date' => '2026-11-20']);
        $completed = $this->leave($employee, ['status' => 'approved', 'start_date' => '2026-11-10', 'end_date' => '2026-11-12']);
        $pending = $this->leave($employee, ['status' => 'pending_supervisor_approval', 'start_date' => '2026-11-25', 'end_date' => '2026-11-26']);

        $this->actingAs($employee->user);
        $this->getJson(route('employee.leave.calendar.events', ['from' => '2026-11-01', 'to' => '2026-11-30']))
            ->assertOk()
            ->assertJsonFragment(['id' => $upcoming->id, 'title' => $employee->full_name.' · Upcoming', 'status' => 'approved', 'period' => 'upcoming'])
            ->assertJsonFragment(['id' => $ongoing->id, 'title' => $employee->full_name.' · Ongoing', 'status' => 'approved', 'period' => 'ongoing'])
            ->assertJsonFragment(['id' => $completed->id, 'title' => $employee->full_name.' · Completed', 'status' => 'approved', 'period' => 'completed'])
            ->assertJsonFragment(['id' => $pending->id, 'status' => 'pending_supervisor_approval'])
            ->assertJsonPath('events.3.period', null);

        $this->getJson(route('employee.leave.calendar.details', $ongoing))
            ->assertOk()
            ->assertJsonPath('status', 'approved')
            ->assertJsonPath('period', 'ongoing');
    }

    public function test_hr_filters_and_date_ranges_preserve_inclusive_and_half_day_data(): void
    {
        $this->travelTo(now('UTC')->setDate(2026, 11, 17)->setTime(16, 15));
        $branch = Branch::factory()->create();
        $owner = Employee::factory()->create(['first_name' => 'Calendar', 'last_name' => 'Tester']);
        $owner->assignments()->create(['branch_id' => $branch->id, 'is_primary' => true, 'effective_from' => now()->subDay()]);
        $supervisor = $this->assign($owner);
        $type = LeaveType::factory()->create();
        $multiDay = $this->leave($owner, [
            'leave_type_id' => $type->id, 'assigned_supervisor_user_id' => $supervisor->id,
            'status' => 'approved', 'start_date' => '2026-11-14', 'end_date' => '2026-11-19',
        ]);
        $this->leave($owner, ['status' => 'approved', 'start_date' => '2026-11-23', 'end_date' => '2026-11-24']);
        $other = Employee::factory()->create();
        $half = $this->leave($other, ['half_day' => 'pm', 'requested_duration' => 0.5, 'start_date' => '2026-11-18', 'end_date' => '2026-11-18']);
        $historical = $this->leave($owner, ['status' => 'cancelled']);
        $revoked = $this->leave($owner, ['status' => 'revoked']);

        $this->actingAs(User::factory()->create(['role_id' => Role::HR]))->get(route('hr.leave.calendar.index'))->assertOk()->assertSee(['Leave Calendar', 'Branch', 'Department']);
        $this->getJson(route('hr.leave.calendar.events', self::RANGE + [
            'branch_id' => $branch->id, 'department_id' => $owner->department_id, 'supervisor_id' => $supervisor->id,
            'employee' => 'Calendar Tester', 'leave_type_id' => $type->id, 'status' => 'approved',
        ]))->assertOk()->assertJsonCount(1, 'events')->assertJsonPath('today', '2026-11-18')
            ->assertJsonPath('events.0.id', $multiDay->id)->assertJsonPath('events.0.start', '2026-11-14')->assertJsonPath('events.0.end', '2026-11-19')
            ->assertJsonPath('staffing.days.2026-11-19.approved', 1)->assertJsonMissingPath('staffing.days.2026-11-20');
        $this->getJson(route('hr.leave.calendar.events', self::RANGE + ['branch_id' => $branch->id, 'department_id' => $other->department_id]))
            ->assertOk()->assertJsonCount(0, 'events');
        $this->getJson(route('hr.leave.calendar.events', self::RANGE))->assertOk()->assertJsonCount(2, 'events')
            ->assertJsonFragment(['id' => $half->id, 'half_day' => 'pm'])->assertJsonMissing(['id' => $historical->id])->assertJsonMissing(['id' => $revoked->id]);
        $this->getJson(route('hr.leave.calendar.events', self::RANGE + ['status' => 'cancelled']))->assertOk()->assertJsonPath('events.0.id', $historical->id);
        $this->getJson(route('hr.leave.calendar.events', self::RANGE + ['status' => 'revoked']))->assertOk()->assertJsonPath('events.0.id', $revoked->id);
        $this->getJson(route('hr.leave.calendar.details', $multiDay))->assertOk()->assertJsonPath('employee_number', $owner->employee_number)
            ->assertJsonPath('branch', $branch->branch_name)->assertJsonPath('supervisor', $supervisor->name)->assertJsonMissingPath('reason');
        $this->getJson(route('hr.leave.calendar.events', ['from' => '2026-11-01', 'to' => '2026-12-31']))->assertUnprocessable()->assertJsonValidationErrors('to');
    }

    public function test_staffing_counts_unique_people_and_flags_only_shared_leave_sessions(): void
    {
        $department = Department::factory()->create();
        [$first, $second, $third, $fourth] = Employee::factory()->count(4)->create(['department_id' => $department->id])->all();
        $supervisor = $this->assign($first);
        foreach ([$second, $third, $fourth] as $employee) {
            $this->assign($employee, $supervisor);
        }
        $schedule = fn (Employee $employee, string $day, string $status, ?string $half = null) => $this->leave($employee, [
            'assigned_supervisor_user_id' => $supervisor->id, 'start_date' => $day, 'end_date' => $day,
            'status' => $status, 'half_day' => $half, 'requested_duration' => $half ? 0.5 : 1,
        ]);

        $schedule($first, '2026-11-17', 'approved', 'am');
        $schedule($first, '2026-11-17', 'approved', 'am');
        $schedule($second, '2026-11-17', 'approved', 'pm');
        $schedule($third, '2026-11-17', 'pending_supervisor_approval', 'am');
        $schedule($fourth, '2026-11-17', 'revoked');
        $schedule($first, '2026-11-18', 'approved');
        $schedule($second, '2026-11-18', 'approved');
        $schedule($third, '2026-11-18', 'pending_supervisor_approval');
        $pending = $schedule($fourth, '2026-11-18', 'pending_supervisor_approval');

        $this->actingAs($supervisor)->getJson(route('supervisor.leave.calendar.events', self::RANGE + ['status' => 'approved']))
            ->assertOk()->assertJsonPath('staffing.employees', 4)
            ->assertJsonPath('staffing.days.2026-11-17', [
                'approved' => 2, 'pending' => 1, 'approved_half_day' => 2, 'overlaps' => ['approved_pending'],
            ])->assertJsonPath('staffing.days.2026-11-18.overlaps', ['approved_approved', 'approved_pending', 'pending_pending'])
            ->assertJsonPath('staffing.days.2026-11-18.approved_half_day', 0)
            ->assertJsonPath('staffing.pending_requests', 3);
        $this->assertSame('pending_supervisor_approval', $pending->fresh()->status);
        $this->assertDatabaseCount('leave_balance_transactions', 0);
    }

    public function test_pagination_keeps_all_events_on_the_same_date_and_rejects_invalid_cursors(): void
    {
        $employee = Employee::factory()->create();
        $type = LeaveType::factory()->create();
        $sameDay = LeaveRequest::factory()->count(151)->create([
            'employee_id' => $employee->id, 'leave_type_id' => $type->id, 'status' => 'approved',
            'start_date' => '2026-11-16', 'end_date' => '2026-11-16',
        ]);

        $this->actingAs($employee->user);
        $first = $this->getJson(route('employee.leave.calendar.events', self::RANGE))
            ->assertOk()->assertJsonCount(150, 'events')->json('next_cursor');
        $this->getJson(route('employee.leave.calendar.events', self::RANGE + ['cursor' => $first]))
            ->assertOk()->assertJsonCount(1, 'events')->assertJsonPath('events.0.id', $sameDay->last()->id);
        $bad = (new Cursor(['calendar_start' => [], 'id' => -1]))->encode();
        $this->getJson(route('employee.leave.calendar.events', self::RANGE + ['cursor' => $bad]))
            ->assertUnprocessable()->assertJsonValidationErrors('cursor');
    }

    private function assign(Employee $employee, ?User $supervisor = null): User
    {
        $supervisor ??= User::factory()->create(['role_id' => Role::SUPERVISOR, 'supervisor_department_id' => $employee->department_id]);
        SupervisorAssignment::factory()->create(['employee_id' => $employee->id, 'supervisor_user_id' => $supervisor->id]);

        return $supervisor;
    }

    private function leave(Employee $employee, array $fields): LeaveRequest
    {
        return LeaveRequest::factory()->create($fields + [
            'employee_id' => $employee->id, 'start_date' => '2026-11-16', 'end_date' => '2026-11-16',
        ]);
    }
}
