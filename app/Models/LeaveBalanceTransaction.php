<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LeaveBalanceTransaction extends Model
{
    use HasFactory;

    protected $fillable = [
        'employee_leave_balance_id', 'leave_request_id', 'leave_entitlement_id', 'reversal_of_transaction_id',
        'authorized_by_user_id', 'transaction_type', 'amount', 'effective_on', 'idempotency_key', 'reason', 'metadata',
    ];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2', 'effective_on' => 'date', 'reason' => 'encrypted', 'metadata' => 'array'];
    }

    public function authorizer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'authorized_by_user_id');
    }

    public function balance(): BelongsTo
    {
        return $this->belongsTo(EmployeeLeaveBalance::class, 'employee_leave_balance_id');
    }

    public function leaveRequest(): BelongsTo
    {
        return $this->belongsTo(LeaveRequest::class);
    }

    public function entitlement(): BelongsTo
    {
        return $this->belongsTo(LeaveEntitlement::class, 'leave_entitlement_id');
    }

    public function reversedTransaction(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reversal_of_transaction_id');
    }
}
