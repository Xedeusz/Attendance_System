<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\Role;
use App\Models\SupervisorAssignment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class LeavePeriodTest extends TestCase
{
    use RefreshDatabase;

    public function test_manila_midnight_changes_the_period_but_preserves_the_approval(): void
    {
        Queue::fake();
        foreach ([['2028-02-29', '2028-03-01'], ['2026-12-31', '2027-01-01'], ['2026-12-06', '2026-12-06']] as [$start, $end]) {
            $first = Carbon::parse($start, 'Asia/Manila')->utc();
            $last = Carbon::parse($end, 'Asia/Manila')->utc();
            $leave = LeaveRequest::factory()->create(['status' => 'approved', 'start_date' => $start, 'end_date' => $end, 'finalized_at' => $first->copy()->subWeek()]);
            $original = $leave->refresh()->getRawOriginal();
            foreach ([[$first->copy()->subSecond(), 'upcoming'], [$first, 'ongoing'], [$last->copy()->addDay()->subSecond(), 'ongoing'], [$last->copy()->addDay(), 'completed']] as [$time, $period]) {
                $this->travelTo($time);
                $this->assertSame($period, $leave->periodStatus());
                $this->assertTrue(LeaveRequest::inPeriod($period)->whereKey($leave)->exists());
            }
            $this->assertSame($original, $leave->fresh()->getRawOriginal());
        }

        foreach ([...LeaveRequest::PENDING, 'rejected', 'cancelled', 'revoked'] as $status) {
            $leave = LeaveRequest::factory()->create(['status' => $status, 'start_date' => $start, 'end_date' => $end]);
            $this->assertNull($leave->periodStatus());
            $this->assertFalse(LeaveRequest::inPeriod('completed')->whereKey($leave)->exists());
        }
        Queue::assertNothingPushed();
        $this->assertDatabaseEmpty('essential_emails');
    }

    public function test_all_three_roles_can_filter_and_view_approved_leave_periods(): void
    {
        $this->travelTo(Carbon::parse('2026-11-19 00:00', 'Asia/Manila'));
        $employee = Employee::factory()->create();
        $supervisor = User::factory()->create(['role_id' => Role::SUPERVISOR, 'supervisor_department_id' => $employee->department_id]);
        SupervisorAssignment::factory()->create(['employee_id' => $employee->id, 'supervisor_user_id' => $supervisor->id]);
        $approved = LeaveRequest::factory()->create(['employee_id' => $employee->id, 'assigned_supervisor_user_id' => $supervisor->id,
            'start_date' => '2026-11-17', 'end_date' => '2026-11-18', 'status' => 'approved']);
        LeaveRequest::factory()->create(['employee_id' => $employee->id, 'assigned_supervisor_user_id' => $supervisor->id, 'status' => 'approved']);

        foreach (['employee' => $employee->user, 'supervisor' => $supervisor, 'hr' => User::factory()->create(['role_id' => Role::HR])] as $role => $user) {
            $this->actingAs($user)->get(route($role.'.leave.index', ['period' => 'completed', 'status' => 'approved']))
                ->assertOk()->assertSee('Leave period: Completed')->assertDontSee('Leave period: Upcoming')
                ->assertViewHas('leaves', fn ($page) => $page->pluck('id')->all() === [$approved->id]);
            $this->get(route($role.'.leave.show', $approved))->assertOk()->assertSee('Approved')->assertSee('Leave period: Completed');
            $this->getJson(route($role.'.leave.calendar.details', $approved))->assertOk()
                ->assertJsonPath('status', 'approved')->assertJsonPath('period', 'completed');
        }
    }
}
