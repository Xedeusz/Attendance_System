<?php

namespace Tests\Feature;

use App\Models\AttendanceEvent;
use App\Models\AttendanceRecord;
use App\Models\Role;
use App\Models\User;
use App\Services\ProfilePhotoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\Support\AttendanceData;
use Tests\TestCase;

class AttendanceAvatarTest extends TestCase
{
    use AttendanceData, RefreshDatabase;

    public function test_current_private_avatar_is_shared_by_attendance_pages_without_changing_camera_evidence(): void
    {
        Storage::fake('local');
        $employee = $this->attendee();
        $this->punch($this->capture($employee))->assertOk();
        $event = AttendanceEvent::sole();
        $original = $event->getAttributes();
        $photo = $event->photo->getAttributes();
        $bytes = Storage::disk('attendance')->get($event->photo->storage_path);
        $owner = app(ProfilePhotoService::class)->update($employee->user, $this->cameraImage(90));
        $this->actingAs($owner);
        $ownerUrl = $owner->avatarData()['url'];
        foreach (['/employee/dashboard', '/employee/attendance/history', route('attendance.show', $event->record)] as $path) {
            $this->get($path)->assertOk()->assertSee('Employee identification')->assertSee($employee->employee_number)->assertSee(hash('sha256', $owner->profile_photo_path), false);
        }
        $this->actingAs($this->reviewer());
        $hrUrl = $owner->avatarData()['url'];
        $this->get('/hr/attendance')->assertOk()->assertSee('attendance-table-avatar')->assertSee($hrUrl, false);
        $this->get(route('attendance.show', $event->record))->assertOk()->assertSee($hrUrl, false)->assertSee(route('attendance.photo', $event->photo));
        $this->get($hrUrl)->assertOk()->assertHeader('Content-Type', 'image/jpeg');
        $updated = app(ProfilePhotoService::class)->update($owner, $this->cameraImage(130));
        $nextUrl = $updated->avatarData()['url'];
        $this->assertNotSame($hrUrl, $nextUrl);
        $this->get(route('attendance.show', $event->record))->assertOk()->assertSee($nextUrl, false)->assertDontSee($hrUrl, false);
        $this->get($hrUrl)->assertNotFound();
        $this->assertSame($original, $event->fresh()->getAttributes());
        $this->assertSame($photo, $event->photo->fresh()->getAttributes());
        $this->assertSame($bytes, Storage::disk('attendance')->get($event->photo->storage_path));
        $this->actingAs($this->attendee()->user)->get($nextUrl)->assertForbidden();
        $supervisor = User::factory()->create(['role_id' => Role::SUPERVISOR]);
        $this->actingAs($supervisor)->get('/hr/attendance')->assertForbidden();
        $this->get(route('attendance.show', $event->record))->assertForbidden();
    }

    public function test_missing_and_removed_photos_render_employee_initials_in_attendance_identity(): void
    {
        Storage::fake('local');
        $employee = $this->attendee();
        $employee->update(['first_name' => 'Kaye', 'last_name' => 'Fernando']);
        $this->punch($this->capture($employee))->assertOk();
        $url = route('attendance.show', AttendanceRecord::sole());
        $this->get($url)->assertOk()->assertSee('KF')->assertSee('Kaye Fernando')->assertSee('attendance-identity-avatar');
        $owner = app(ProfilePhotoService::class)->update($employee->user, $this->cameraImage(80));
        $oldUrl = $owner->avatarData()['url'];
        app(ProfilePhotoService::class)->update($owner, null);
        $this->actingAs($this->reviewer())->get($url)->assertOk()->assertSee('KF')->assertDontSee($oldUrl, false);
    }

    public function test_monitoring_avatar_relationship_queries_do_not_grow_with_the_number_of_employees(): void
    {
        $employee = $this->attendee();
        $this->punch($this->capture($employee))->assertOk();
        $hr = $this->reviewer();
        $this->actingAs($hr);
        DB::enableQueryLog();
        DB::flushQueryLog();
        $this->get('/hr/attendance')->assertOk();
        $one = count(DB::getQueryLog());
        DB::disableQueryLog();
        for ($i = 0; $i < 4; $i++) {
            $other = $this->attendee();
            $this->punch($this->capture($other), [], 90 + $i * 20)->assertOk();
        }
        $this->actingAs($hr->fresh());
        DB::enableQueryLog();
        DB::flushQueryLog();
        $this->get('/hr/attendance')->assertOk();
        $many = count(DB::getQueryLog());
        DB::disableQueryLog();
        $this->assertSame($one, $many, 'Avatar rendering must use a fixed number of eager-loaded relationship queries.');
    }
}
