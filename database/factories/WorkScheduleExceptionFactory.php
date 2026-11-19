<?php

namespace Database\Factories;

use App\Models\Employee;
use App\Models\Role;
use App\Models\User;
use App\Models\WorkScheduleException;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<WorkScheduleException> */
class WorkScheduleExceptionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'employee_id' => Employee::factory(), 'work_date' => '2026-12-25', 'kind' => 'excused',
            'reason' => 'Illustrative authorized absence', 'active' => true,
            'created_by' => User::factory()->state(['role_id' => Role::HR]),
        ];
    }
}
