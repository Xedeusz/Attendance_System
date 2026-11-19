<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkSchedule extends Model
{
    use HasFactory;

    public const GRACE_METHODS = ['from_start' => 'Count from scheduled start after grace', 'after_grace' => 'Count only minutes beyond grace'];

    protected $fillable = ['previous_id', 'employee_id', 'branch_id', 'department_id', 'created_by', 'name', 'description', 'starts_at', 'ends_at', 'break_starts_at', 'break_ends_at', 'paid_break', 'grace_minutes', 'grace_method', 'working_days', 'effective_from', 'effective_until', 'active'];

    protected function casts(): array
    {
        return ['paid_break' => 'boolean', 'active' => 'boolean', 'working_days' => 'array', 'effective_from' => 'date', 'effective_until' => 'date', 'grace_minutes' => 'integer'];
    }

    public function scopeEffectiveOn(Builder $query, string $date): Builder
    {
        return $query->where('active', true)->whereDate('effective_from', '<=', $date)
            ->where(fn (Builder $end) => $end->whereNull('effective_until')->orWhere('effective_until', '>=', $date));
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function snapshot(): array
    {
        return [
            ...$this->only(['id', 'name', 'starts_at', 'ends_at', 'break_starts_at', 'break_ends_at', 'paid_break', 'grace_minutes', 'grace_method', 'working_days']),
            'effective_from' => $this->effective_from->toDateString(),
            'effective_until' => $this->effective_until?->toDateString(),
        ];
    }
}
