<?php

namespace Database\Factories;

use App\Models\LeaveType;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<LeaveType> */
class LeaveTypeFactory extends Factory
{
    /** @return array<string,mixed> */
    public function definition(): array
    {
        return ['code' => strtoupper(fake()->unique()->bothify('QA-????##')), 'name' => 'Synthetic Leave', 'description' => 'Configured test leave policy.', 'requires_document' => false, 'affects_balance' => false, 'allows_half_day' => false, 'is_active' => true, 'duration_policy' => 'requested_days'];
    }
}
