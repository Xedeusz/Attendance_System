<?php

namespace Tests\Support;

use App\Models\Branch;
use App\Models\Employee;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;

trait AttendanceData
{
    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('attendance');
        $this->withCredentials();
        $this->travelTo(now()->startOfSecond());
    }

    protected function attendee(): Employee
    {
        $employee = Employee::factory()->create();
        $branch = Branch::factory()->create(['latitude' => 14.5995, 'longitude' => 120.9842, 'geofence_radius_meters' => 150]);
        $employee->assignments()->create(['branch_id' => $branch->id, 'is_primary' => true, 'effective_from' => now()->subDay()]);

        return $employee;
    }

    protected function reviewer(): User
    {
        return User::factory()->create(['role_id' => Role::HR]);
    }

    protected function capture(Employee $employee, string $action = 'time_in'): array
    {
        $this->actingAs($employee->user);
        $this->get('/employee/attendance')->assertOk();
        $capture = $this->postJson('/employee/attendance/captures', ['action' => $action])->assertOk()->json();
        $this->withCookie(config('session.cookie'), session()->getId());

        return $capture;
    }

    protected function position(array $overrides = []): array
    {
        return array_replace(['latitude' => '14.5995', 'longitude' => '120.9842', 'accuracy' => '5', 'retrieved_at' => (string) now()->valueOf()], $overrides);
    }

    protected function cameraImage(int $color = 40): UploadedFile
    {
        $image = imagecreatetruecolor(640, 480);
        imagefill($image, 0, 0, imagecolorallocate($image, $color, 90, 180));
        ob_start();
        imagejpeg($image, null, 82);
        $bytes = ob_get_clean();
        imagedestroy($image);

        return UploadedFile::fake()->createWithContent('camera.jpg', $bytes);
    }

    protected function punch(array $capture, array $overrides = [], int $color = 40): TestResponse
    {
        return $this->post('/employee/attendance/captures/'.$capture['id'].'/submit', array_replace(['token' => $capture['token'], 'photo' => $this->cameraImage($color), ...$this->position()], $overrides), ['Accept' => 'application/json']);
    }
}
