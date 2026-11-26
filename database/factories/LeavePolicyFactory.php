<?php

namespace Database\Factories;

use App\Models\LeavePolicy;
use App\Models\LeaveType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LeavePolicy>
 */
class LeavePolicyFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'leave_type_id' => LeaveType::factory()->state(['affects_balance' => true]),
            'effective_from' => '2026-01-01',
            'allocation_basis' => 'annual',
            'default_days' => 15,
            'day_basis' => 'working_days',
            'carryover_limit' => 0,
            'proration_rule' => 'none',
            'eligibility_rules' => null,
            'is_active' => true,
        ];
    }
}
