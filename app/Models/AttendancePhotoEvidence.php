<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AttendancePhotoEvidence extends Model
{
    protected $table = 'attendance_photo_evidence';

    protected $fillable = ['attendance_event_id', 'storage_disk', 'storage_path', 'original_format', 'mime_type', 'file_size', 'image_width', 'image_height', 'file_hash', 'purged_at'];

    protected $hidden = ['storage_path', 'file_hash'];

    protected function casts(): array
    {
        return ['purged_at' => 'datetime'];
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(AttendanceEvent::class, 'attendance_event_id');
    }
}
