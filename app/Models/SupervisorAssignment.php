<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupervisorAssignment extends Model
{
    use HasFactory;

    protected $fillable = ['supervisor_user_id', 'employee_id', 'effective_from', 'effective_until', 'is_active', 'revoked_at', 'assigned_by_user_id'];

    protected function casts(): array
    {
        return ['effective_from' => 'date', 'effective_until' => 'date', 'is_active' => 'boolean', 'revoked_at' => 'datetime'];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function supervisor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'supervisor_user_id');
    }

    public function scopeCurrent(Builder $query): Builder
    {
        $today = now(config('attendance.timezone'))->toDateString();

        return $query->where('is_active', true)->whereDate('effective_from', '<=', $today)->where(fn ($q) => $q->whereNull('effective_until')->orWhereDate('effective_until', '>=', $today))
            ->whereHas('supervisor', fn ($q) => $q->where('role_id', Role::SUPERVISOR)->where('account_status', 'active')->where(fn ($q) => $q->whereDoesntHave('employee')->orWhereHas('employee', fn ($q) => $q->where('employment_status', 'active'))));
    }
}
