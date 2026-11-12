<?php

namespace Tests\Feature;

use App\Models\AttendanceEvent;
use App\Models\AttendancePhotoEvidence;
use App\Models\User;
use App\Services\AuditService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Storage;
use Tests\Support\AttendanceData;
use Tests\TestCase;

class AttendanceSecurityTest extends TestCase
{
    use AttendanceData, RefreshDatabase;

    public function test_authentication_employee_eligibility_and_hr_boundaries_are_enforced(): void
    {
        $this->postJson('/employee/attendance/captures', ['action' => 'time_in'])->assertUnauthorized();
        $this->actingAs(User::factory()->create())->postJson('/employee/attendance/captures', ['action' => 'time_in'])->assertUnprocessable();
        $employee = $this->attendee();
        $this->actingAs($employee->user);
        $this->get('/hr/attendance')->assertForbidden();
        $employee->user->forceFill(['account_status' => 'deactivated'])->save();
        $this->postJson('/employee/attendance/captures', ['action' => 'time_in'])->assertRedirect('/login');
        $this->assertDatabaseCount('attendance_events', 0);
    }

    public function test_capture_ownership_token_and_spoofed_fields_cannot_be_bypassed(): void
    {
        $owner = $this->attendee();
        $capture = $this->capture($owner);
        $this->punch($capture, ['token' => str_repeat('a', 64)])->assertForbidden();
        foreach (['employee_id' => 99, 'branch_id' => 99, 'occurred_at' => '2000-01-01', 'verification_status' => 'location_validated'] as $field => $value) {
            $this->punch($capture, [$field => $value])->assertUnprocessable()->assertJsonValidationErrors($field);
        }
        $this->actingAs($this->attendee()->user);
        $this->punch($capture)->assertForbidden();
        $this->getJson('/employee/attendance/captures/'.$capture['id'].'/status')->assertForbidden();
        $this->assertDatabaseCount('attendance_events', 0);
    }

    public function test_expiry_and_changed_branch_configuration_invalidate_unconsumed_capture(): void
    {
        $employee = $this->attendee();
        $capture = $this->capture($employee);
        $this->travel(181)->seconds();
        $this->punch($capture)->assertConflict();
        $capture = $this->capture($employee);
        $employee->currentAssignment->branch->update(['geofence_radius_meters' => 200]);
        $this->punch($capture)->assertConflict();
        $this->assertDatabaseCount('attendance_events', 0);
        $this->assertCount(0, Storage::disk('attendance')->allFiles());
    }

    public function test_missing_inactive_and_future_branch_assignments_cannot_clock_in(): void
    {
        $employee = $this->attendee();
        $this->actingAs($employee->user);
        $assignment = $employee->currentAssignment;
        $assignment->branch->update(['status' => 'inactive']);
        $this->postJson('/employee/attendance/captures', ['action' => 'time_in'])->assertUnprocessable();
        $assignment->branch->update(['status' => 'active']);
        $assignment->update(['effective_from' => now()->addDay()]);
        $this->postJson('/employee/attendance/captures', ['action' => 'time_in'])->assertUnprocessable();
        $assignment->delete();
        $this->postJson('/employee/attendance/captures', ['action' => 'time_in'])->assertUnprocessable();
    }

    public function test_outside_inaccurate_malformed_and_unconfigured_locations_never_pass_normally(): void
    {
        $employee = $this->attendee();
        $capture = $this->capture($employee);
        foreach ([['latitude' => 14.7], ['accuracy' => 100], ['latitude' => 91], ['longitude' => 181], ['retrieved_at' => now()->subMinute()->valueOf()]] as $input) {
            $this->punch($capture, $input)->assertUnprocessable();
        }
        $employee->currentAssignment->branch->update(['latitude' => null, 'longitude' => null, 'geofence_radius_meters' => null]);
        $this->punch($this->capture($employee))->assertUnprocessable();
        $this->assertDatabaseCount('attendance_events', 0);
    }

    public function test_malformed_unsupported_oversized_and_tiny_images_are_rejected(): void
    {
        $capture = $this->capture($this->attendee());
        foreach ([UploadedFile::fake()->createWithContent('fake.jpg', '<?php echo "unsafe";'), UploadedFile::fake()->create('large.jpg', 2049, 'image/jpeg'), UploadedFile::fake()->image('tiny.png', 1, 1)] as $photo) {
            $this->punch($capture, ['photo' => $photo])->assertUnprocessable();
        }
        $this->assertDatabaseCount('attendance_events', 0);
        $this->assertCount(0, Storage::disk('attendance')->allFiles());
    }

    public function test_photos_are_encrypted_and_only_owner_or_hr_can_read_evidence(): void
    {
        $owner = $this->attendee();
        $this->punch($this->capture($owner))->assertOk();
        $photo = AttendancePhotoEvidence::sole();
        $event = AttendanceEvent::sole();
        $encrypted = Storage::disk('attendance')->get($photo->storage_path);
        $this->assertStringStartsWith("\xff\xd8", Crypt::decryptString($encrypted));
        $this->assertStringNotContainsString('14.5995', $event->getRawOriginal('location'));
        $this->get('/attendance/photos/'.$photo->id)->assertOk()->assertHeader('Content-Type', 'image/jpeg')->assertHeader('X-Content-Type-Options', 'nosniff');
        $other = $this->attendee();
        $this->actingAs($other->user);
        $this->get('/attendance/photos/'.$photo->id)->assertForbidden();
        $this->get('/employee/attendance/records/'.$event->attendance_record_id)->assertForbidden();
        $this->get('/employee/attendance/history')->assertOk()->assertDontSee('View record');
        $this->actingAs($this->reviewer())->get('/attendance/photos/'.$photo->id)->assertOk();
        $this->get('/employee/attendance/records/'.$event->attendance_record_id)->assertOk()->assertSee('Employee-reported, untrusted coordinates');
    }

    public function test_failed_transaction_cleans_staged_files_and_rolls_back_all_attendance_facts(): void
    {
        $capture = $this->capture($this->attendee());
        Exceptions::fake();
        $this->mock(AuditService::class)->shouldReceive('record')->andThrow(new \RuntimeException('Synthetic audit failure'));
        $this->punch($capture)->assertServerError();
        $this->assertDatabaseCount('attendance_events', 0);
        $this->assertDatabaseCount('attendance_records', 0);
        $this->assertDatabaseCount('attendance_photo_evidence', 0);
        $this->assertCount(0, Storage::disk('attendance')->allFiles());
    }

    public function test_capture_creation_is_rate_limited_and_permissions_are_page_specific(): void
    {
        $employee = $this->attendee();
        $this->actingAs($employee->user);
        $this->get('/employee/attendance')->assertHeader('Permissions-Policy', 'camera=(self), microphone=(), geolocation=(self)');
        $this->get('/employee/dashboard')->assertHeader('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');
        for ($i = 0; $i < 20; $i++) {
            $this->postJson('/employee/attendance/captures', ['action' => 'time_in'])->assertOk();
        }
        $this->postJson('/employee/attendance/captures', ['action' => 'time_in'])->assertTooManyRequests();
    }
}
