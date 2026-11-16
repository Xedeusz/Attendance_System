<?php

namespace Tests\Feature;

use App\Models\AttendanceRecord;
use App\Models\AuditLog;
use App\Services\AttendanceQueries;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\Support\AttendanceData;
use Tests\TestCase;

class AttendanceWorkflowTest extends TestCase
{
    use AttendanceData, RefreshDatabase;

    public function test_time_in_and_overnight_time_out_use_server_time_and_separate_evidence(): void
    {
        $this->travelTo(now()->setDate(2026, 11, 12)->setTime(15, 50));
        $employee = $this->attendee();
        $inTime = now()->copy();
        $this->punch($this->capture($employee))->assertOk()->assertJsonPath('verification_status', 'location_validated');
        $record = AttendanceRecord::sole();
        $this->assertSame('2026-11-12', $record->work_date->toDateString());
        $this->assertTrue($record->time_in_at->equalTo($inTime));
        $this->travel(9)->hours();
        $this->punch($this->capture($employee, 'time_out'), [], 180)->assertOk()->assertJsonPath('record_id', $record->id);
        $record->refresh();
        $this->assertSame('completed', $record->session_status);
        $this->assertSame('2026-11-12', $record->work_date->toDateString());
        $this->assertTrue($record->time_out_at->equalTo(now()));
        $this->assertNull($record->classification);
        $this->assertDatabaseCount('attendance_records', 1);
        $this->assertDatabaseCount('attendance_events', 2);
        $this->assertCount(2, Storage::disk('attendance')->allFiles());
        $this->assertTrue(AuditLog::where('action', 'attendance.time_out_recorded')->exists());
        $this->postJson('/employee/attendance/captures', ['action' => 'time_out'])->assertUnprocessable();
    }

    public function test_identical_retries_return_original_result_and_conflicting_replays_are_rejected(): void
    {
        $capture = $this->capture($this->attendee());
        $position = $this->position();
        $first = $this->punch($capture, $position)->assertOk()->json();
        $this->travel(5)->minutes();
        $this->punch($capture, $position)->assertOk()->assertJsonPath('event_id', $first['event_id'])->assertJsonPath('occurred_at', $first['occurred_at']);
        $this->punch($capture, $position, 120)->assertConflict();
        $this->getJson('/employee/attendance/captures/'.$capture['id'].'/status')->assertOk()->assertJsonPath('submitted', true);
        $this->assertDatabaseCount('attendance_events', 1);
        $this->assertCount(1, Storage::disk('attendance')->allFiles());
    }

    public function test_competing_tabs_cannot_create_two_open_sessions(): void
    {
        $employee = $this->attendee();
        $first = $this->capture($employee);
        $stale = $this->capture($employee);
        $this->punch($first)->assertOk();
        $this->punch($stale)->assertConflict();
        $this->postJson('/employee/attendance/captures', ['action' => 'time_in'])->assertUnprocessable();
        $this->assertDatabaseCount('attendance_records', 1);
        $this->assertCount(1, Storage::disk('attendance')->allFiles());
    }

    public function test_missing_time_in_camera_or_location_never_creates_accepted_attendance(): void
    {
        $employee = $this->attendee();
        $this->actingAs($employee->user);
        $this->postJson('/employee/attendance/captures', ['action' => 'time_out'])->assertUnprocessable();
        $capture = $this->capture($employee);
        $this->punch($capture, ['photo' => null])->assertUnprocessable()->assertJsonValidationErrors('photo');
        $this->punch($capture, ['latitude' => null, 'longitude' => null, 'accuracy' => null, 'retrieved_at' => null])->assertUnprocessable();
        $this->post('/employee/attendance/assistance', ['action' => 'time_out', 'reason' => 'Camera unavailable on my device.'])->assertRedirect();
        $this->assertDatabaseCount('attendance_assistance_requests', 1);
        $this->assertDatabaseCount('attendance_records', 0);
        $this->assertTrue(AuditLog::where('action', 'attendance.validation_failed')->exists());
    }

    public function test_fresh_photo_and_fresh_location_are_required_for_checkout(): void
    {
        $employee = $this->attendee();
        $this->punch($this->capture($employee))->assertOk();
        $out = $this->capture($employee, 'time_out');
        $this->punch($out)->assertUnprocessable();
        $this->punch($out, $this->position(['retrieved_at' => now()->subMinutes(2)->valueOf()]), 180)->assertUnprocessable();
        $this->assertDatabaseCount('attendance_events', 1);
        $this->assertSame('open', AttendanceRecord::sole()->session_status);
        $this->punch($out, [], 180)->assertOk();
    }

    public function test_history_filters_and_hr_totals_are_driven_by_saved_records(): void
    {
        $employee = $this->attendee();
        $this->punch($this->capture($employee))->assertOk();
        $this->get('/employee/dashboard')->assertOk()->assertSee('Open attendance')
            ->assertSee('Your attendance session is open')
            ->assertViewHas('open', fn ($record) => $record?->employee_id === $employee->id && $record->session_status === 'open');
        $this->get('/employee/attendance/history?to='.now()->toDateString())->assertOk()->assertSee($employee->currentAssignment->branch->branch_name);
        $this->get('/employee/attendance/history?sort=password')->assertSessionHasErrors('sort');
        $this->get('/employee/attendance/history?employee_id='.$employee->id)->assertSessionHasErrors('employee_id');
        $this->actingAs($this->reviewer())->get('/hr/attendance?employee_id='.$employee->id)->assertOk()->assertSee($employee->employee_number);
        $this->get('/hr/dashboard')->assertOk()->assertSee('Time Ins today');
        $this->assertSame(1, app(AttendanceQueries::class)->statistics()['Currently clocked in']);
    }

    public function test_server_clock_regression_does_not_create_a_checkout_before_time_in(): void
    {
        $employee = $this->attendee();
        $this->punch($this->capture($employee))->assertOk();
        $capture = $this->capture($employee, 'time_out');
        $this->travel(-1)->minutes();
        $this->punch($capture, [], 180)->assertUnprocessable();
        $this->assertDatabaseCount('attendance_events', 1);
    }

    public function test_manila_daily_totals_exclude_the_following_midnight(): void
    {
        $this->travelTo(now()->setDate(2026, 11, 12)->setTime(16, 0));
        $employee = $this->attendee();
        $this->punch($this->capture($employee))->assertOk();
        $this->travelTo(now()->setTime(15, 59));
        $this->assertSame(0, app(AttendanceQueries::class)->statistics()['Time Ins today']);
        $this->travelTo(now()->setTime(16, 0));
        $this->assertSame(1, app(AttendanceQueries::class)->statistics()['Time Ins today']);
    }
}
