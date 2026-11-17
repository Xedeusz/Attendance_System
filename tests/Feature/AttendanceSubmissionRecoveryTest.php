<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\AttendanceData;
use Tests\TestCase;

class AttendanceSubmissionRecoveryTest extends TestCase
{
    use AttendanceData, RefreshDatabase;

    public function test_status_reads_do_not_exhaust_the_submission_or_start_limits(): void
    {
        $employee = $this->attendee();
        $capture = $this->capture($employee);
        for ($i = 0; $i < 30; $i++) {
            $this->getJson('/employee/attendance/captures/'.$capture['id'].'/status')->assertOk();
        }

        $this->punch($capture)->assertOk()->assertJsonPath('attendance.canTimeIn', false)->assertJsonPath('attendance.canTimeOut', true);
        $this->postJson('/employee/attendance/captures', ['action' => 'time_out'])->assertOk();
        $this->assertDatabaseCount('attendance_events', 1);
    }

    public function test_exhausted_submit_limit_does_not_block_status_recovery(): void
    {
        $capture = $this->capture($this->attendee());
        $position = $this->position();
        $event = $this->punch($capture, $position)->assertOk()->json('event_id');
        for ($i = 1; $i < 20; $i++) {
            $this->punch($capture, $position)->assertOk()->assertJsonPath('event_id', $event);
        }
        $this->punch($capture, $position)->assertTooManyRequests()->assertHeader('Retry-After');

        $this->getJson('/employee/attendance/captures/'.$capture['id'].'/status')
            ->assertOk()->assertJsonPath('status', 'succeeded')->assertJsonPath('result.event_id', $event);
        $this->assertDatabaseCount('attendance_events', 1);
    }

    public function test_unconsumed_capture_remains_unknown_until_expiry_prevents_execution(): void
    {
        $capture = $this->capture($this->attendee());
        $this->getJson('/employee/attendance/captures/'.$capture['id'].'/status')
            ->assertOk()->assertJsonPath('status', 'unknown')->assertJsonPath('submitted', false);
        $this->travel(181)->seconds();

        $this->getJson('/employee/attendance/captures/'.$capture['id'].'/status')
            ->assertOk()->assertJsonPath('status', 'not_recorded')->assertJsonPath('attendance.canTimeIn', true)->assertJsonPath('attendance.canTimeOut', false);
        $this->punch($capture)->assertConflict();
        $this->assertDatabaseCount('attendance_events', 0);
    }

    public function test_lost_reply_can_be_reconciled_after_capture_expiry_without_resubmitting(): void
    {
        $capture = $this->capture($this->attendee());
        $result = $this->punch($capture)->assertOk()->json();
        $this->travel(5)->minutes();

        $this->getJson('/employee/attendance/captures/'.$capture['id'].'/status')
            ->assertOk()->assertJsonPath('status', 'succeeded')
            ->assertJsonPath('result.occurred_at', $result['occurred_at'])
            ->assertJsonPath('result.attendance.canTimeOut', true);
        $this->assertDatabaseCount('attendance_events', 1);
    }
}
