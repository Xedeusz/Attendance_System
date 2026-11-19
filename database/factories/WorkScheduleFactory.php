<?php

namespace Database\Factories;

use App\Models\Role;
use App\Models\User;
use App\Models\WorkSchedule;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<WorkSchedule> */
class WorkScheduleFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => 'Office schedule', 'starts_at' => '08:00', 'ends_at' => '16:00',
            'break_starts_at' => '12:00', 'break_ends_at' => '13:00', 'paid_break' => false,
            'grace_minutes' => 10, 'grace_method' => 'from_start', 'working_days' => [1, 2, 3, 4, 5],
            'effective_from' => '2026-01-01', 'active' => true,
            'created_by' => User::factory()->state(['role_id' => Role::HR]),
        ];
    }
}
