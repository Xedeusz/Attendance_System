<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LeaveApprovalDocument extends Model
{
    use HasFactory;

    protected $fillable = ['leave_request_id', 'approved_by_user_id', 'supervisor_signature_id', 'document_reference', 'storage_path', 'sha256_hash', 'version', 'template_reference', 'approver_name', 'generated_at', 'verification_token_hash', 'revoked_at', 'revoked_by_user_id', 'superseded_by_document_id'];

    protected $hidden = ['storage_path', 'verification_token_hash'];

    public function leave(): BelongsTo
    {
        return $this->belongsTo(LeaveRequest::class, 'leave_request_id');
    }

    public function signature(): BelongsTo
    {
        return $this->belongsTo(SupervisorSignature::class, 'supervisor_signature_id');
    }

    protected function casts(): array
    {
        return ['generated_at' => 'datetime', 'revoked_at' => 'datetime'];
    }
}
