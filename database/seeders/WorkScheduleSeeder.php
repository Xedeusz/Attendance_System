<?php

namespace Database\Seeders;

use App\Models\WorkSchedule;
use Illuminate\Database\Seeder;

class WorkScheduleSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment('testing')) {
            return;
        }

        WorkSchedule::factory()->create(['name' => 'Regular Office Schedule (example)', 'active' => false]);
    }
}
