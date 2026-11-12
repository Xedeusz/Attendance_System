<?php

namespace Tests\Feature;

use App\Models\AttendanceEvent;
use App\Models\AttendanceRecord;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\Support\AttendanceData;
use Tests\TestCase;

class AttendanceRetentionTest extends TestCase
{
    use AttendanceData, RefreshDatabase;

    public function test_expired_evidence_is_deleted_without_changing_attendance_time(): void
    {
        $employee = $this->attendee();
        $this->punch($this->capture($employee))->assertOk();
        $event = AttendanceEvent::sole();
        $path = $event->photo->storage_path;
        $time = $event->occurred_at;
        $this->travel(31)->days();
        $this->artisan('attendance:prune-evidence')->assertSuccessful();
        Storage::disk('attendance')->assertMissing($path);
        $event->refresh();
        $this->assertNull($event->location);
        $this->assertNull($event->photo->storage_path);
        $this->assertTrue(AttendanceRecord::sole()->time_in_at->equalTo($time));
        $this->get('/attendance/photos/'.$event->photo->id)->assertGone();
    }

    public function test_pending_evidence_has_a_bounded_hold_and_cannot_be_approved_after_purge(): void
    {
        $employee = $this->attendee();
        $this->punch($this->capture($employee), ['accuracy' => 100, 'request_review' => 1, 'exception_reason' => 'GPS is unreliable inside the office.'])->assertOk();
        $event = AttendanceEvent::sole();
        $path = $event->photo->storage_path;
        $this->travel(31)->days();
        $this->artisan('attendance:prune-evidence')->assertSuccessful();
        Storage::disk('attendance')->assertExists($path);
        $this->travel(60)->days();
        $this->artisan('attendance:prune-evidence')->assertSuccessful();
        Storage::disk('attendance')->assertMissing($path);
        $this->actingAs($this->reviewer())->post('/hr/attendance/events/'.$event->id.'/review', ['decision' => 'approved', 'remarks' => 'Attempted review after retention expiry.', 'confirmation' => 1])->assertSessionHasErrors('review');
        $this->assertDatabaseCount('attendance_reviews', 0);
    }
}
