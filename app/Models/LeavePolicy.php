<?php

namespace App\Models;

use Database\Factories\LeavePolicyFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LeavePolicy extends Model
{
    /** @use HasFactory<LeavePolicyFactory> */
    use HasFactory;

    protected $fillable = [
        'leave_type_id', 'effective_from', 'effective_until', 'allocation_basis', 'default_days',
        'day_basis', 'carryover_limit', 'proration_rule', 'eligibility_rules', 'is_active', 'created_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'effective_from' => 'date', 'effective_until' => 'date', 'default_days' => 'decimal:2',
            'carryover_limit' => 'decimal:2', 'eligibility_rules' => 'array', 'is_active' => 'boolean',
        ];
    }

    public function type(): BelongsTo
    {
        return $this->belongsTo(LeaveType::class, 'leave_type_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function entitlements(): HasMany
    {
        return $this->hasMany(LeaveEntitlement::class);
    }
}
