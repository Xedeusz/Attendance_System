<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class AttendanceEvent extends Model
{
    protected $fillable = ['attendance_record_id', 'employee_id', 'branch_id', 'capture_session_id', 'event_type', 'occurred_at', 'location', 'accuracy_meters', 'distance_meters', 'geofence_status', 'verification_status', 'review_status', 'exception_reason', 'branch_snapshot', 'location_purged_at'];

    protected $hidden = ['location', 'exception_reason'];

    protected function casts(): array
    {
        return ['location' => 'encrypted:array', 'exception_reason' => 'encrypted', 'occurred_at' => 'datetime', 'branch_snapshot' => 'array', 'location_purged_at' => 'datetime'];
    }

    public function record(): BelongsTo
    {
        return $this->belongsTo(AttendanceRecord::class, 'attendance_record_id');
    }

    public function photo(): HasOne
    {
        return $this->hasOne(AttendancePhotoEvidence::class);
    }

    public function review(): HasOne
    {
        return $this->hasOne(AttendanceReview::class);
    }
}
