<?php

namespace Tests\Feature;

use App\Models\AttendanceEvent;
use App\Models\Branch;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\AttendanceData;
use Tests\TestCase;

class HrDashboardTest extends TestCase
{
    use AttendanceData, RefreshDatabase;

    public function test_hr_dashboard_has_honest_empty_states_and_zero_counts(): void
    {
        $this->actingAs($this->reviewer())->get('/hr/dashboard')
            ->assertOk()->assertSee('Workforce Overview')
            ->assertSee('Development workspace')
            ->assertSee('No attendance activity recorded yet')->assertSee('All caught up')
            ->assertSee('No recent employee records.')->assertSee('No pending account activations.')
            ->assertViewHas('stats', ['Total employees' => 0, 'Active employee accounts' => 0, 'Pending account activations' => 0, 'Active branches' => 0, 'Departments' => 0])
            ->assertViewHas('attendanceStats', ['Time Ins today' => 0, 'Time Outs today' => 0, 'Currently clocked in' => 0, 'Completed sessions today' => 0, 'Pending HR review' => 0, 'Open over 24 hours' => 0, 'Rejected submissions' => 0, 'Geofence exceptions today' => 0])
            ->assertViewHas('activity', fn ($days) => count($days) === 7 && array_sum(array_column($days, 'time_in')) === 0 && array_sum(array_column($days, 'time_out')) === 0);
    }

    public function test_company_counts_recent_records_and_pending_accounts_use_saved_data(): void
    {
        $active = Employee::factory()->create();
        $pending = Employee::factory()->for(User::factory()->state(['account_status' => 'pending_activation']))->create();
        $inactive = Employee::factory()->for(User::factory()->state(['account_status' => 'deactivated']))->create();
        $branch = Branch::factory()->create();
        Branch::factory()->create(['status' => 'inactive']);
        $active->assignments()->create(['branch_id' => $branch->id, 'is_primary' => true, 'effective_from' => now()->subDay()]);

        $this->actingAs($this->reviewer())->get('/hr/dashboard')->assertOk()
            ->assertViewHas('stats', ['Total employees' => 3, 'Active employee accounts' => 1, 'Pending account activations' => 1, 'Active branches' => 1, 'Departments' => 3])
            ->assertViewHas('recent', fn ($records) => $records->modelKeys() === [$inactive->id, $pending->id, $active->id])
            ->assertViewHas('pending', fn ($records) => $records->modelKeys() === [$pending->id])
            ->assertSee($active->employee_number)->assertSee($branch->branch_name)
            ->assertSee('account_status=pending_activation', false);
    }

    public function test_production_dashboard_hides_development_workspace_indicator(): void
    {
        $this->app['env'] = 'production';

        $this->actingAs($this->reviewer())->get('/hr/dashboard')->assertOk()
            ->assertDontSee('Development workspace')->assertSee('Workforce Overview');
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

        $response = $this->actingAs($reviewer)->get('/hr/dashboard')->assertOk()->assertSee('View activity data');
        $days = $response->viewData('activity');
        $this->assertSame(['2026-11-10', '2026-11-11', '2026-11-12', '2026-11-13', '2026-11-14', '2026-11-15', '2026-11-16'], array_column($days, 'date'));
        $this->assertSame([0, 0, 0, 0, 0, 1, 1], array_column($days, 'time_in'));
        $this->assertSame([1, 0, 0, 0, 0, 0, 1], array_column($days, 'time_out'));
        $response->assertViewHas('attendanceStats', ['Time Ins today' => 1, 'Time Outs today' => 1, 'Currently clocked in' => 1, 'Completed sessions today' => 1, 'Pending HR review' => 1, 'Open over 24 hours' => 0, 'Rejected submissions' => 0, 'Geofence exceptions today' => 2]);
    }
}
