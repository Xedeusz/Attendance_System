<?php

namespace Tests\Feature;

use App\Models\AttendanceAssistanceRequest;
use App\Models\AttendanceEvent;
use App\Models\AttendanceRecord;
use App\Models\AuditLog;
use App\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\Support\AttendanceData;
use Tests\TestCase;

class AttendanceReviewTest extends TestCase
{
    use AttendanceData, RefreshDatabase;

    private function decision(AttendanceEvent $event, string $decision = 'approved'): TestResponse
    {
        return $this->post('/hr/attendance/events/'.$event->id.'/review', ['decision' => $decision, 'remarks' => 'Reviewed synthetic office evidence.', 'confirmation' => 1]);
    }

    public function test_failed_geofence_requires_explicit_review_and_approval_preserves_original_facts(): void
    {
        $employee = $this->attendee();
        $capture = $this->capture($employee);
        $originalTime = now()->copy();
        $this->punch($capture, ['latitude' => 14.7, 'request_review' => 1, 'exception_reason' => 'Indoor positioning does not reflect my office.'])->assertOk()->assertJsonPath('verification_status', 'pending_review');
        $event = AttendanceEvent::sole();
        $record = AttendanceRecord::sole();
        $this->assertNull($record->time_in_at);
        $this->assertSame('pending', $record->session_status);
        $this->get('/employee/dashboard')->assertOk()->assertSee('Awaiting HR review')
            ->assertViewHas('open', fn ($open) => $open?->id === $record->id);
        $this->postJson('/employee/attendance/captures', ['action' => 'time_in'])->assertUnprocessable();
        $this->get('/notifications')->assertOk()->assertSee('Awaiting HR review');
        $this->decision($event)->assertForbidden();
        $this->actingAs($this->reviewer());
        $this->travel(10)->minutes();
        $this->decision($event)->assertRedirect()->assertSessionHasNoErrors();
        $event->refresh();
        $record->refresh();
        $this->assertSame('outside_geofence', $event->geofence_status);
        $this->assertSame('exception_approved', $event->verification_status);
        $this->assertTrue($event->occurred_at->equalTo($originalTime));
        $this->assertTrue($record->time_in_at->equalTo($originalTime));
        $this->assertSame('open', $record->session_status);
        $this->assertNotNull($event->photo);
        $this->assertNotNull($event->location);
        $this->assertTrue(AuditLog::where('action', 'attendance.review_approved')->exists());
        $this->decision($event, 'rejected')->assertConflict();
        $this->assertDatabaseCount('attendance_reviews', 1);
    }

    public function test_rejected_time_in_is_retained_and_allows_a_new_capture(): void
    {
        $employee = $this->attendee();
        $this->punch($this->capture($employee), ['accuracy' => 100, 'request_review' => 1, 'exception_reason' => 'GPS accuracy is poor inside the building.'])->assertOk();
        $event = AttendanceEvent::sole();
        $this->actingAs($this->reviewer());
        $this->decision($event, 'rejected')->assertRedirect();
        $this->assertSame('cancelled', AttendanceRecord::sole()->session_status);
        $this->assertNotNull($event->fresh()->photo);
        $this->assertNotNull($event->fresh()->location);
        $this->punch($this->capture($employee), [], 180)->assertOk();
        $this->assertDatabaseCount('attendance_records', 2);
    }

    public function test_rejected_checkout_keeps_original_session_open_and_accepts_a_fresh_checkout(): void
    {
        $employee = $this->attendee();
        $this->punch($this->capture($employee))->assertOk();
        $this->punch($this->capture($employee, 'time_out'), ['latitude' => 14.7, 'request_review' => 1, 'exception_reason' => 'GPS incorrectly places me outside the branch.'], 150)->assertOk();
        $out = AttendanceEvent::where('event_type', 'time_out')->sole();
        $this->actingAs($this->reviewer());
        $this->decision($out, 'rejected')->assertRedirect();
        $record = AttendanceRecord::sole();
        $this->assertSame('open', $record->session_status);
        $this->assertNull($record->time_out_at);
        $this->punch($this->capture($employee, 'time_out'), [], 230)->assertOk();
        $this->assertSame('completed', $record->fresh()->session_status);
        $this->assertDatabaseCount('attendance_events', 3);
    }

    public function test_review_requires_confirmation_remarks_pending_state_and_separate_hr_reviewer(): void
    {
        $employee = $this->attendee();
        $employee->user->forceFill(['role_id' => Role::HR])->save();
        $this->punch($this->capture($employee), ['accuracy' => 100, 'request_review' => 1, 'exception_reason' => 'Location accuracy requires a manual review.'])->assertOk();
        $event = AttendanceEvent::sole();
        $this->decision($event)->assertForbidden();
        $this->actingAs($this->reviewer());
        $this->post('/hr/attendance/events/'.$event->id.'/review', ['decision' => 'approved', 'remarks' => 'short'])->assertSessionHasErrors(['confirmation', 'remarks']);
        $this->assertDatabaseCount('attendance_reviews', 0);
    }

    public function test_assistance_resolution_creates_no_attendance_facts(): void
    {
        $employee = $this->attendee();
        $this->actingAs($employee->user);
        $this->post('/employee/attendance/assistance', ['action' => 'time_in', 'reason' => 'No camera is available on this workstation.'])->assertRedirect();
        $id = AttendanceAssistanceRequest::sole()->id;
        $this->actingAs($this->reviewer());
        $this->post('/hr/attendance/assistance/'.$id, ['status' => 'resolved', 'remarks' => 'Arranged access to an approved office device.', 'confirmation' => 1])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseCount('attendance_records', 0);
        $this->assertDatabaseHas('attendance_assistance_requests', ['id' => $id, 'status' => 'resolved']);
    }
}
