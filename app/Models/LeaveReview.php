<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LeaveReview extends Model
{
    use HasFactory;

    protected $fillable = ['leave_request_id', 'reviewer_user_id', 'action', 'remarks', 'metadata', 'reviewed_at'];

    protected function casts(): array
    {
        return ['remarks' => 'encrypted', 'metadata' => 'array', 'reviewed_at' => 'datetime'];
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewer_user_id');
    }
}
