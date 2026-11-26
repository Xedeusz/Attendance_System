<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class LeaveRequest extends Model
{
    use HasFactory;

    public const PENDING = ['awaiting_supervisor_assignment', 'pending_supervisor_approval'];

    public const PERIODS = ['upcoming' => 'Upcoming', 'ongoing' => 'Ongoing', 'completed' => 'Completed'];

    public function periodStatus(): ?string
    {
        if ($this->status !== 'approved') {
            return null;
        }

        $today = now(config('attendance.timezone'))->toDateString();

        return match (true) {
            $this->start_date->toDateString() > $today => 'upcoming',
            $this->end_date->toDateString() < $today => 'completed',
            default => 'ongoing',
        };
    }

    public function scopeInPeriod(Builder $query, string $period): Builder
    {
        $today = now(config('attendance.timezone'))->startOfDay();
        $tomorrow = $today->copy()->addDay()->toDateString();
        $query->where('status', 'approved');

        return match ($period) {
            'upcoming' => $query->where('start_date', '>=', $tomorrow),
            'completed' => $query->where('end_date', '<', $today->toDateString()),
            'ongoing' => $query->where('start_date', '<', $tomorrow)->where('end_date', '>=', $today->toDateString()),
        };
    }

    protected $fillable = ['employee_id', 'leave_type_id', 'assigned_supervisor_user_id', 'start_date', 'end_date', 'requested_duration', 'reserved_duration', 'charged_duration', 'half_day', 'reason', 'type_snapshot', 'form_snapshot', 'status', 'submitted_at', 'reviewed_at', 'finalized_at', 'cancelled_at'];

    protected function casts(): array
    {
        return ['start_date' => 'date', 'end_date' => 'date', 'submitted_at' => 'datetime', 'reviewed_at' => 'datetime', 'finalized_at' => 'datetime', 'cancelled_at' => 'datetime', 'requested_duration' => 'decimal:2', 'reserved_duration' => 'decimal:2', 'charged_duration' => 'decimal:2', 'reason' => 'encrypted', 'type_snapshot' => 'array', 'form_snapshot' => 'encrypted:array'];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function type(): BelongsTo
    {
        return $this->belongsTo(LeaveType::class, 'leave_type_id');
    }

    public function supervisor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_supervisor_user_id');
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(LeaveReview::class)->orderBy('id');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(LeaveSupportingDocument::class);
    }

    public function documentsOfApproval(): HasMany
    {
        return $this->hasMany(LeaveApprovalDocument::class);
    }

    public function approvalDocument(): HasOne
    {
        return $this->hasOne(LeaveApprovalDocument::class)->latestOfMany('version');
    }

    public function balanceTransaction(): HasOne
    {
        return $this->hasOne(LeaveBalanceTransaction::class);
    }

    public function scopeForSupervisor(Builder $query, User $actor): Builder
    {
        return $query->where('assigned_supervisor_user_id', $actor->id)->whereIn('employee_id', SupervisorAssignment::current()->where('supervisor_user_id', $actor->id)->select('employee_id'))->whereHas('employee', fn ($q) => $q->where('user_id', '!=', $actor->id));
    }
}
