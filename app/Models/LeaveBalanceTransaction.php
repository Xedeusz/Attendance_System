<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LeaveBalanceTransaction extends Model
{
    use HasFactory;

    protected $fillable = ['employee_leave_balance_id', 'leave_request_id', 'authorized_by_user_id', 'transaction_type', 'amount', 'reason'];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2', 'reason' => 'encrypted'];
    }

    public function authorizer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'authorized_by_user_id');
    }
}
