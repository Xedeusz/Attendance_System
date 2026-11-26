<?php

namespace App\Models;

use Database\Factories\SpecialLeaveEligibilityActivityFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SpecialLeaveEligibilityActivity extends Model
{
    /** @use HasFactory<SpecialLeaveEligibilityActivityFactory> */
    use HasFactory;

    public $timestamps = false;

    protected $fillable = ['special_leave_eligibility_request_id', 'actor_user_id', 'action', 'previous_status', 'new_status', 'remarks', 'created_at'];

    protected $hidden = ['remarks'];

    protected function casts(): array
    {
        return ['remarks' => 'encrypted', 'created_at' => 'datetime'];
    }

    public function request(): BelongsTo
    {
        return $this->belongsTo(SpecialLeaveEligibilityRequest::class, 'special_leave_eligibility_request_id');
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }
}
