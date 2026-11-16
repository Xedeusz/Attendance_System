<?php

namespace Database\Factories;

use App\Models\EmployeeLeaveBalance;
use App\Models\LeaveBalanceTransaction;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<LeaveBalanceTransaction> */
class LeaveBalanceTransactionFactory extends Factory
{
    /** @return array<string,mixed> */
    public function definition(): array
    {
        return ['employee_leave_balance_id' => EmployeeLeaveBalance::factory(), 'authorized_by_user_id' => User::factory()->state(['role_id' => Role::HR]), 'transaction_type' => 'hr_adjustment', 'amount' => 0, 'reason' => 'Synthetic ledger fixture; no official company credit.'];
    }
}
