<?php

namespace Database\Seeders;

use App\Models\AttendanceHoliday;
use Illuminate\Database\Seeder;

class AttendanceHolidaySeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment('testing')) {
            return;
        }

        AttendanceHoliday::factory()->create(['status' => 'cancelled']);
    }
}
