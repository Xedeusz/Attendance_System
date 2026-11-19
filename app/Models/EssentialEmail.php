<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EssentialEmail extends Model
{
    use HasFactory;

    public const ACCOUNT_SETUP = 'account_setup';

    public const PASSWORD_RESET = 'password_reset';

    public const LEAVE_SUBMITTED = 'leave_submitted';

    public const LEAVE_APPROVED = 'leave_approved';

    public const LEAVE_REJECTED = 'leave_rejected';

    protected $fillable = [
        'event_key', 'type', 'recipient_user_id', 'recipient_email', 'account_invitation_id',
        'leave_request_id', 'leave_review_id', 'leave_approval_document_id', 'document_reference',
        'auth_token', 'link_base', 'expires_at', 'status', 'enqueued_at',
    ];

    protected $hidden = ['recipient_email', 'auth_token'];

    protected $attributes = ['status' => 'queued', 'attempts' => 0, 'attachment_included' => false];

    protected function casts(): array
    {
        return [
            'recipient_email' => 'encrypted', 'auth_token' => 'encrypted', 'attachment_included' => 'boolean',
            'attempts' => 'integer', 'expires_at' => 'datetime', 'enqueued_at' => 'datetime',
            'last_attempt_at' => 'datetime', 'processed_at' => 'datetime',
        ];
    }

    public function recipient(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recipient_user_id');
    }

    public function invitation(): BelongsTo
    {
        return $this->belongsTo(AccountInvitation::class, 'account_invitation_id');
    }

    public function leave(): BelongsTo
    {
        return $this->belongsTo(LeaveRequest::class, 'leave_request_id');
    }

    public function review(): BelongsTo
    {
        return $this->belongsTo(LeaveReview::class, 'leave_review_id');
    }
}
