<?php

namespace Database\Factories;

use App\Models\Employee;
use App\Models\EmployeeLeaveBalance;
use App\Models\LeaveType;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<EmployeeLeaveBalance> */
class EmployeeLeaveBalanceFactory extends Factory
{
    /** @return array<string,mixed> */
    public function definition(): array
    {
        return ['employee_id' => Employee::factory(), 'leave_type_id' => LeaveType::factory()->state(['affects_balance' => true]), 'policy_period' => now()->format('Y'), 'available_days' => 0];
    }
}
