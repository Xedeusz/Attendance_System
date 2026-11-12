<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class DepartmentFactory extends Factory
{
    public function definition(): array
    {
        return ['department_code' => fake()->unique()->bothify('QA-####??'), 'department_name' => 'Synthetic QA Department', 'status' => 'active'];
    }
}
