<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LeaveSupportingDocument extends Model
{
    use HasFactory;

    protected $fillable = ['leave_request_id', 'storage_path', 'original_name', 'mime_type', 'file_size', 'purge_after', 'purged_at'];

    protected $hidden = ['storage_path'];

    protected function casts(): array
    {
        return ['purge_after' => 'datetime', 'purged_at' => 'datetime'];
    }

    public function leave(): BelongsTo
    {
        return $this->belongsTo(LeaveRequest::class, 'leave_request_id');
    }
}
