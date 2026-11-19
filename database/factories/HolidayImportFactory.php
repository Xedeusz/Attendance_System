<?php

namespace Database\Factories;

use App\Models\HolidayImport;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<HolidayImport> */
class HolidayImportFactory extends Factory
{
    public function definition(): array
    {
        return [
            'approved_by' => User::factory()->state(['role_id' => Role::HR]), 'year' => 2026,
            'source_url' => 'https://example.gov.ph/proclamations/fixture', 'declaration' => 'Synthetic test declaration',
            'content_hash' => fake()->unique()->sha256(), 'imported_count' => 1,
        ];
    }
}
