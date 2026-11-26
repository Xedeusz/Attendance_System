<?php

namespace Database\Factories;

use App\Models\Employee;
use App\Models\LeaveEntitlement;
use App\Models\LeaveType;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<LeaveEntitlement>
 */
class LeaveEntitlementFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'employee_id' => Employee::factory(),
            'leave_type_id' => LeaveType::factory()->state(['affects_balance' => true]),
            'leave_policy_id' => null,
            'source' => 'annual_allocation',
            'reference' => 'annual-2026',
            'period_start' => '2026-01-01',
            'period_end' => '2026-12-31',
            'granted_days' => 15,
            'eligibility_status' => 'not_required',
            'idempotency_key' => fn () => 'factory:'.Str::uuid(),
        ];
    }
}
