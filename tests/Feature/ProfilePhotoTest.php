<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use App\Services\AuditService;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\AttendanceData;
use Tests\TestCase;

class ProfilePhotoTest extends TestCase
{
    use AttendanceData,RefreshDatabase;

    public static function formats(): array
    {
        return [['jpg'], ['png'], ['webp']];
    }

    #[DataProvider('formats')]
    public function test_supported_images_are_private_normalized_and_owner_only(string $extension): void
    {
        Storage::fake('local');
        $employee = $this->attendee();
        $this->actingAs($employee->user);
        $response = $this->postJson('/employee/profile/photo', ['photo' => UploadedFile::fake()->image('portrait.'.$extension, 200, 300)])->assertOk();
        $path = $employee->user->fresh()->profile_photo_path;
        Storage::disk('local')->assertExists($path);
        $response->assertJsonMissingPath('profile_photo_path');
        $this->assertStringNotContainsString($path, $employee->user->fresh()->toJson());
        $url = $response->json('avatar.url');
        $this->get($url)->assertOk()->assertHeader('Content-Type', 'image/jpeg')->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->actingAs($this->attendee()->user)->get($url)->assertNotFound();
        $this->assertDatabaseHas('audit_logs', ['action' => 'profile.photo_updated', 'subject_id' => $employee->user_id]);
    }

    public function test_replacement_removal_and_missing_files_restore_initials_without_touching_attendance(): void
    {
        Storage::fake('local');
        $employee = $this->attendee();
        $this->actingAs($employee->user);
        $this->postJson('/employee/profile/photo', ['photo' => $this->cameraImage()])->assertOk();
        $old = $employee->user->fresh()->profile_photo_path;
        $response = $this->postJson('/employee/profile/photo', ['photo' => $this->cameraImage(90)])->assertOk();
        $current = $employee->user->fresh()->profile_photo_path;
        Storage::disk('local')->assertMissing($old);
        Storage::disk('local')->assertExists($current);
        Storage::disk('local')->delete($current);
        $this->get($response->json('avatar.url'))->assertNotFound();
        $this->deleteJson('/employee/profile/photo')->assertOk()->assertJsonPath('avatar.url', null);
        $this->assertNull($employee->user->fresh()->profile_photo_path);
        $employee->update(['first_name' => 'Dex', 'last_name' => 'De Guzman']);
        $this->assertSame('DD', $employee->user->fresh()->avatarData()['initials']);
        $this->assertDatabaseCount('attendance_events', 0);
    }

    public function test_invalid_oversized_and_spoofed_uploads_preserve_the_existing_photo(): void
    {
        Storage::fake('local');
        $employee = $this->attendee();
        $this->actingAs($employee->user);
        $this->postJson('/employee/profile/photo', ['photo' => $this->cameraImage()])->assertOk();
        $old = $employee->user->fresh()->profile_photo_path;
        foreach ([UploadedFile::fake()->createWithContent('fake.jpg', '<?php unsafe();'), UploadedFile::fake()->create('large.jpg', 2049, 'image/jpeg'), UploadedFile::fake()->createWithContent('x.svg', '<svg></svg>')] as $file) {
            $this->postJson('/employee/profile/photo', ['photo' => $file])->assertUnprocessable();
        }
        foreach (['user_id', 'department_id', 'role_id', 'profile_photo_path'] as $field) {
            $this->postJson('/employee/profile/photo', ['photo' => $this->cameraImage(), $field => '99'])->assertUnprocessable();
        }
        $this->assertSame($old, $employee->user->fresh()->profile_photo_path);
        Storage::disk('local')->assertExists($old);
    }

    public function test_only_employees_with_profiles_can_write_and_csrf_remains_enforced(): void
    {
        Storage::fake('local');
        $this->postJson('/employee/profile/photo', ['photo' => $this->cameraImage()])->assertUnauthorized();
        foreach ([User::factory()->create(['role_id' => Role::HR]), User::factory()->create(['role_id' => Role::SUPERVISOR]), User::factory()->create()] as $user) {
            $this->actingAs($user);
            $this->postJson('/employee/profile/photo', ['photo' => $this->cameraImage()])->assertForbidden();
            $this->deleteJson('/employee/profile/photo')->assertForbidden();
        }
        $this->actingAs($this->attendee()->user);
        $this->app['env'] = 'local';
        $this->postJson('/employee/profile/photo', ['photo' => $this->cameraImage()])->assertStatus(419);
    }

    public function test_failed_transaction_keeps_the_original_photo_and_cleans_staged_evidence(): void
    {
        Storage::fake('local');
        $employee = $this->attendee();
        $this->actingAs($employee->user);
        $this->postJson('/employee/profile/photo', ['photo' => $this->cameraImage()])->assertOk();
        $old = $employee->user->fresh()->profile_photo_path;
        Exceptions::fake();
        $this->mock(AuditService::class)->shouldReceive('record')->andThrow(new \RuntimeException('Synthetic failure'));
        $this->postJson('/employee/profile/photo', ['photo' => $this->cameraImage(90)])->assertServerError();
        $this->assertSame($old, $employee->user->fresh()->profile_photo_path);
        $this->assertCount(1, Storage::disk('local')->allFiles('profile-photos'));
    }

    public function test_disk_write_failure_preserves_the_current_picture(): void
    {
        Storage::fake('local');
        $employee = $this->attendee();
        $this->actingAs($employee->user);
        $this->postJson('/employee/profile/photo', ['photo' => $this->cameraImage()])->assertOk();
        $old = $employee->user->fresh()->profile_photo_path;
        $disk = \Mockery::mock(FilesystemAdapter::class);
        $disk->shouldReceive('put')->once()->andReturnFalse();
        $disk->shouldReceive('delete')->once()->andReturnTrue();
        Storage::shouldReceive('disk')->with('local')->andReturn($disk);
        Exceptions::fake();

        $this->postJson('/employee/profile/photo', ['photo' => $this->cameraImage(90)])->assertServerError();

        $this->assertSame($old, $employee->user->fresh()->profile_photo_path);
        Exceptions::assertReported(\RuntimeException::class);
    }
}
