<?php

namespace Database\Seeders;

use App\Models\AttendanceHoliday;
use App\Models\HolidayImport;
use Illuminate\Database\Seeder;

class HolidayImportSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment('testing')) {
            return;
        }

        $import = HolidayImport::factory()->create();
        AttendanceHoliday::factory()->create([
            'holiday_import_id' => $import->id, 'created_by' => $import->approved_by, 'type' => 'regular',
            'source_url' => $import->source_url, 'declaration' => $import->declaration, 'status' => 'cancelled',
        ]);
    }
}
