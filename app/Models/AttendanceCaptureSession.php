<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class AttendanceCaptureSession extends Model
{
    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = ['id', 'employee_id', 'branch_id', 'assignment_id', 'attendance_record_id', 'action', 'token_hash', 'session_hash', 'attendance_version', 'branch_snapshot', 'expires_at', 'consumed_at', 'request_hash'];

    protected $hidden = ['token_hash', 'session_hash', 'request_hash'];

    protected function casts(): array
    {
        return ['branch_snapshot' => 'array', 'expires_at' => 'datetime', 'consumed_at' => 'datetime', 'attendance_version' => 'integer'];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function event(): HasOne
    {
        return $this->hasOne(AttendanceEvent::class, 'capture_session_id');
    }
}
