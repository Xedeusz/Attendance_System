<?php

namespace App\Models;

use Database\Factories\SpecialLeaveEligibilityDocumentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SpecialLeaveEligibilityDocument extends Model
{
    /** @use HasFactory<SpecialLeaveEligibilityDocumentFactory> */
    use HasFactory;

    protected $fillable = ['special_leave_eligibility_request_id', 'storage_path', 'original_name', 'mime_type', 'size_bytes', 'document_category', 'uploaded_by_user_id'];

    public function request(): BelongsTo
    {
        return $this->belongsTo(SpecialLeaveEligibilityRequest::class, 'special_leave_eligibility_request_id');
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by_user_id');
    }
}
