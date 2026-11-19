<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AttendanceComputation extends Model
{
    use HasFactory;

    protected $fillable = ['attendance_record_id', 'attendance_correction_request_id', 'work_schedule_id', 'actor_id', 'version', 'trigger', 'reason', 'policy', 'holiday_types', 'timestamps_used', 'expected_minutes', 'recorded_minutes', 'unpaid_break_minutes', 'net_minutes', 'late_minutes', 'undertime_minutes', 'incomplete'];

    protected function casts(): array
    {
        return [
            'policy' => 'array', 'holiday_types' => 'array', 'timestamps_used' => 'array', 'incomplete' => 'boolean',
            'work_schedule_id' => 'integer', 'expected_minutes' => 'integer', 'recorded_minutes' => 'integer',
            'unpaid_break_minutes' => 'integer', 'net_minutes' => 'integer', 'late_minutes' => 'integer', 'undertime_minutes' => 'integer',
        ];
    }
}
