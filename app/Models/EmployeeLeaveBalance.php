<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EmployeeLeaveBalance extends Model
{
    use HasFactory;

    protected $fillable = ['employee_id', 'leave_type_id', 'policy_period', 'available_days'];

    protected function casts(): array
    {
        return ['available_days' => 'decimal:2'];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function type(): BelongsTo
    {
        return $this->belongsTo(LeaveType::class, 'leave_type_id');
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(LeaveBalanceTransaction::class)->latest('id');
    }
}
