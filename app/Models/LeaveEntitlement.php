<?php

namespace App\Models;

use Database\Factories\LeaveEntitlementFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LeaveEntitlement extends Model
{
    /** @use HasFactory<LeaveEntitlementFactory> */
    use HasFactory;

    protected $fillable = [
        'employee_id', 'leave_type_id', 'leave_policy_id', 'source', 'reference', 'period_start', 'period_end',
        'event_date', 'event_variant', 'granted_days', 'eligibility_status', 'verification_notes',
        'verified_by_user_id', 'verified_at', 'created_by_user_id', 'idempotency_key', 'special_leave_eligibility_request_id',
    ];

    protected $hidden = ['verification_notes'];

    protected function casts(): array
    {
        return [
            'period_start' => 'date', 'period_end' => 'date', 'event_date' => 'date', 'granted_days' => 'decimal:2',
            'verified_at' => 'datetime', 'verification_notes' => 'encrypted',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function type(): BelongsTo
    {
        return $this->belongsTo(LeaveType::class, 'leave_type_id');
    }

    public function policy(): BelongsTo
    {
        return $this->belongsTo(LeavePolicy::class, 'leave_policy_id');
    }

    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by_user_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(LeaveBalanceTransaction::class);
    }

    public function eligibilityRequest(): BelongsTo
    {
        return $this->belongsTo(SpecialLeaveEligibilityRequest::class, 'special_leave_eligibility_request_id');
    }
}
