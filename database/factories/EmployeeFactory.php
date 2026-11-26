<?php

namespace Database\Factories;

use App\Models\Department;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class EmployeeFactory extends Factory
{
    public function definition(): array
    {
        return ['user_id' => User::factory(), 'employee_number' => fake()->unique()->bothify('QA-######??'), 'first_name' => 'Synthetic', 'last_name' => 'Employee', 'sex' => 'female', 'department_id' => Department::factory(), 'position_title' => 'QA fixture', 'date_hired' => '2026-01-01', 'employment_status' => 'active'];
    }
}
