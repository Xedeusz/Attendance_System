<?php

namespace Database\Factories;

use App\Models\AttendanceHoliday;
use App\Models\Role;
use App\Models\User;
use App\Services\HolidayCsvImport;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<AttendanceHoliday> */
class AttendanceHolidayFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->word().' company holiday', 'holiday_date' => '2026-12-25',
            'type' => 'company', 'status' => 'active', 'work_expected' => false, 'scope' => 'company',
            'created_by' => User::factory()->state(['role_id' => Role::HR]),
            'updated_by' => fn (array $attributes) => $attributes['created_by'],
            'identity_hash' => fn (array $attributes) => HolidayCsvImport::identity(
                $attributes['holiday_date'], $attributes['name'], $attributes['scope'], $attributes['locations'] ?? [],
            ),
        ];
    }
}
