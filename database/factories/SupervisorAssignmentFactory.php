<?php

namespace Database\Factories;

use App\Models\Employee;
use App\Models\Role;
use App\Models\SupervisorAssignment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<SupervisorAssignment> */
class SupervisorAssignmentFactory extends Factory
{
    /** @return array<string,mixed> */
    public function definition(): array
    {
        return ['supervisor_user_id' => User::factory()->state(['role_id' => Role::SUPERVISOR]), 'employee_id' => Employee::factory(), 'effective_from' => now(config('attendance.timezone'))->subDay()->toDateString(), 'effective_until' => null, 'is_active' => true, 'assigned_by_user_id' => User::factory()->state(['role_id' => Role::HR])];
    }
}
