<?php

namespace Database\Seeders;

use App\Models\AttendanceComputation;
use Illuminate\Database\Seeder;

class AttendanceComputationSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment('testing')) {
            return;
        }

        AttendanceComputation::factory()->create();
    }
}
