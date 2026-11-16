<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AttendanceCorrectionRequest extends Model
{
    use HasFactory;

    protected $fillable = ['employee_id', 'attendance_record_id', 'branch_id', 'correction_type', 'requested_date', 'requested_time_in_at', 'requested_time_out_at', 'original_values', 'record_revision', 'reason', 'status', 'pending_key', 'submitted_at', 'reviewed_by_user_id', 'reviewed_at', 'review_remarks'];

    protected function casts(): array
    {
        return ['record_revision' => 'integer', 'requested_date' => 'date', 'requested_time_in_at' => 'datetime', 'requested_time_out_at' => 'datetime', 'original_values' => 'array', 'reason' => 'encrypted', 'review_remarks' => 'encrypted', 'submitted_at' => 'datetime', 'reviewed_at' => 'datetime'];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function record(): BelongsTo
    {
        return $this->belongsTo(AttendanceRecord::class, 'attendance_record_id');
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by_user_id');
    }
}
