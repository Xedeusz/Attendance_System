<?php

namespace Database\Seeders;

use App\Models\WorkScheduleException;
use Illuminate\Database\Seeder;

class WorkScheduleExceptionSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment('testing')) {
            return;
        }

        WorkScheduleException::factory()->create(['active' => false]);
    }
}
