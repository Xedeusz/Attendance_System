<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PrivacyNoticeAcknowledgement extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = ['user_id', 'privacy_notice_id', 'acknowledged_at'];

    protected function casts(): array
    {
        return ['acknowledged_at' => 'immutable_datetime'];
    }

    /** @return BelongsTo<PrivacyNotice, $this> */
    public function privacyNotice(): BelongsTo
    {
        return $this->belongsTo(PrivacyNotice::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
