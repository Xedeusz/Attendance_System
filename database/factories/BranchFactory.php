<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class BranchFactory extends Factory
{
    public function definition(): array
    {
        return ['branch_code' => fake()->unique()->bothify('QA-####??'), 'branch_name' => 'Synthetic QA Office', 'address' => 'Test address', 'city' => 'Test city', 'province' => 'Test province', 'status' => 'active'];
    }
}
