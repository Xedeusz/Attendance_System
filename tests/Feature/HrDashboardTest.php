<?php

namespace Tests\Feature;

use App\Models\AttendanceCorrectionRequest;
use App\Models\AttendanceEvent;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\User;
use App\Services\AttendanceQueries;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\AttendanceData;
use Tests\TestCase;

class HrDashboardTest extends TestCase
{
    use AttendanceData, RefreshDatabase;

    public function test_dashboard_has_only_the_requested_metrics_and_a_compact_empty_state(): void
    {
        $this->actingAs($this->reviewer())->get('/hr/dashboard')
            ->assertOk()->assertSee('Daily HR Overview')->assertSee('HR Action Center')
            ->assertSee('All caught up!')->assertSee('No pending HR actions at the moment.')
            ->assertDontSee('Active branches')->assertDontSee('Time Ins today')->assertDontSee('Attendance Activity')
            ->assertViewHas('stats', ['Active Employees' => 0, 'Present Today' => 0, 'On Approved Leave' => 0, 'Pending HR Actions' => 0])
            ->assertViewHas('todayAttendance', ['Employees Late' => 0, 'Employees with Undertime' => 0, 'Incomplete Attendance' => 0])
            ->assertViewHas('actions', []);
    }

    public function test_daily_leave_and_action_counts_deduplicate_employees_and_exclude_other_statuses(): void
    {
        $this->travelTo(now()->setDate(2026, 11, 15)->setTime(16, 30, 0));
        $active = Employee::factory()->create();
        $pending = Employee::factory()->for(User::factory()->state(['account_status' => 'pending_activation']))->create();
        Employee::factory()->for(User::factory()->state(['account_status' => 'deactivated']))->create();

        LeaveRequest::factory()->for($active)->count(2)->create(['status' => 'approved', 'start_date' => '2026-11-16', 'end_date' => '2026-11-16']);
        LeaveRequest::factory()->for($pending)->create(['status' => 'approved', 'start_date' => '2026-11-15', 'end_date' => '2026-11-15']);
        LeaveRequest::factory()->for($active)->count(2)->create(['status' => 'awaiting_supervisor_assignment']);
        LeaveRequest::factory()->for($active)->create(['status' => 'pending_supervisor_approval']);
        AttendanceCorrectionRequest::factory()->for($active)->create(['status' => 'pending']);
        AttendanceCorrectionRequest::factory()->for($active)->create(['status' => 'approved']);

        $this->actingAs($this->reviewer())->get('/hr/dashboard')->assertOk()
            ->assertViewHas('stats', ['Active Employees' => 1, 'Present Today' => 0, 'On Approved Leave' => 1, 'Pending HR Actions' => 2])
            ->assertViewHas('actions', fn (array $actions): bool => array_column($actions, 'count') === [1, 1])
            ->assertSee('datetime="2026-11-16"', false)
            ->assertSee('status=awaiting_supervisor_assignment', false)
            ->assertDontSee('Pending Supervisor approval')
            ->assertDontSee('Pending account activations');
    }

    public function test_production_dashboard_hides_development_workspace_indicator(): void
    {
        $this->app['env'] = 'production';

        $this->actingAs($this->reviewer())->get('/hr/dashboard')->assertOk()
            ->assertDontSee('Development workspace')->assertSee('Daily HR Overview');
    }

    public function test_seven_day_activity_uses_manila_midnights_and_accepted_events_only(): void
    {
        $this->travelTo(now()->setDate(2026, 11, 9)->setTime(15, 59, 59));
        $employee = $this->attendee();
        $this->punch($this->capture($employee))->assertOk();
        $this->travelTo(now()->setTime(16, 0, 0));
        $this->punch($this->capture($employee, 'time_out'), [], 110)->assertOk();
        $this->travelTo(now()->setDate(2026, 11, 15)->setTime(15, 59, 59));
        $this->punch($this->capture($employee), [], 130)->assertOk();
        $this->travelTo(now()->setTime(16, 0, 0));
        $this->punch($this->capture($employee, 'time_out'), [], 150)->assertOk();
        $reviewer = $this->reviewer();
        $pendingEmployee = $this->attendee();
        $this->punch($this->capture($pendingEmployee), ['latitude' => 14.7, 'request_review' => 1, 'exception_reason' => 'Indoor GPS needs review.'], 170)->assertOk();
        $approvedEmployee = $this->attendee();
        $this->punch($this->capture($approvedEmployee), ['latitude' => 14.7, 'request_review' => 1, 'exception_reason' => 'Indoor GPS needs review.'], 190)->assertOk();
        $approved = AttendanceEvent::where('employee_id', $approvedEmployee->id)->sole();
        $this->actingAs($reviewer)->post('/hr/attendance/events/'.$approved->id.'/review', ['decision' => 'approved', 'remarks' => 'Reviewed synthetic evidence.', 'confirmation' => 1])->assertRedirect()->assertSessionHasNoErrors();
        $this->travelTo(now()->setDate(2026, 11, 16)->setTime(4, 0, 0));

        $this->actingAs($reviewer)->get('/hr/dashboard')->assertOk()
            ->assertViewHas('stats', fn (array $stats): bool => $stats['Present Today'] === 1 && $stats['Pending HR Actions'] === 1)
            ->assertSee('Attendance submissions awaiting HR review');
        $days = app(AttendanceQueries::class)->activity();
        $this->assertSame(['2026-11-10', '2026-11-11', '2026-11-12', '2026-11-13', '2026-11-14', '2026-11-15', '2026-11-16'], array_column($days, 'date'));
        $this->assertSame([0, 0, 0, 0, 0, 1, 1], array_column($days, 'time_in'));
        $this->assertSame([1, 0, 0, 0, 0, 0, 1], array_column($days, 'time_out'));
    }
}
