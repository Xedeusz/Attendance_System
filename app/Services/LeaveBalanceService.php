<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\EmployeeLeaveBalance;
use App\Models\LeaveBalanceTransaction;
use App\Models\LeaveEntitlement;
use App\Models\LeavePolicy;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\User;
use App\Support\LeaveDays;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class LeaveBalanceService
{
    /** @return array{period:string,entitled:float,approved:float,pending:float,remaining:float,available:float,balance:?EmployeeLeaveBalance,event_reference:?string,eligibility_status:?string} */
    public function summary(int|Employee $employee, int|LeaveType $type, string $period, ?int $excludeLeaveRequestId = null): array
    {
        $employeeId = $employee instanceof Employee ? $employee->id : $employee;
        $typeId = $type instanceof LeaveType ? $type->id : $type;
        $balance = EmployeeLeaveBalance::query()
            ->where('employee_id', $employeeId)
            ->where('leave_type_id', $typeId)
            ->where('policy_period', $period)
            ->first();

        $pending = $this->pendingDays($employeeId, $typeId, $period, $excludeLeaveRequestId);

        $remaining = (float) ($balance?->available_days ?? 0);
        $eventEntitlement = LeaveEntitlement::query()
            ->where('employee_id', $employeeId)
            ->where('leave_type_id', $typeId)
            ->whereNotNull('event_date')
            ->whereDate('period_start', '<=', $period.'-12-31')
            ->whereDate('period_end', '>=', $period.'-01-01')
            ->latest('event_date')
            ->first();
        $approved = $balance
            ? max(0, -(float) $balance->transactions()->whereIn('transaction_type', ['approved_debit', 'historical_approved_debit', 'approval_reversal'])->sum('amount'))
            : 0;

        return [
            'period' => $period,
            'entitled' => $remaining + $approved,
            'approved' => $approved,
            'pending' => (float) $pending,
            'remaining' => $remaining,
            'available' => max(0, $remaining - (float) $pending),
            'balance' => $balance,
            'event_reference' => $eventEntitlement?->reference,
            'eligibility_status' => $eventEntitlement?->eligibility_status,
        ];
    }

    /** @return Collection<int, array{type:LeaveType,period:string,entitled:float,approved:float,pending:float,remaining:float,available:float,balance:?EmployeeLeaveBalance,event_reference:?string,eligibility_status:?string}> */
    public function summaries(Employee $employee, string $period): Collection
    {
        return LeaveType::query()
            ->with('policies')
            ->where('affects_balance', true)
            ->where('is_active', true)
            ->orderBy('name')
            ->get()
            ->map(function (LeaveType $type) use ($employee, $period): array {
                $policy = $type->policies->first(fn (LeavePolicy $policy): bool => $policy->is_active
                    && $policy->effective_from->toDateString() <= $period.'-12-31'
                    && (! $policy->effective_until || $policy->effective_until->toDateString() >= $period.'-01-01'));

                return ['type' => $type, 'policy_basis' => $policy?->allocation_basis] + $this->summary($employee, $type, $period);
            })
            ->filter(fn (array $summary): bool => $summary['policy_basis'] === 'annual' || $summary['balance'] !== null)
            ->values();
    }

    public function reserve(Employee $employee, LeaveType $type, string $period, float $days): EmployeeLeaveBalance
    {
        $balance = EmployeeLeaveBalance::query()
            ->where('employee_id', $employee->id)
            ->where('leave_type_id', $type->id)
            ->where('policy_period', $period)
            ->lockForUpdate()
            ->first();

        if (! $balance) {
            throw ValidationException::withMessages(['leave_type_id' => 'No leave entitlement is configured for this type and policy year. Contact HR.']);
        }

        $remaining = (float) $balance->available_days;
        $pending = $this->pendingDays($employee->id, $type->id, $period, lock: true);
        $available = max(0, $remaining - $pending);
        if ($this->units($days) > $this->units($available)) {
            if ($this->units($available) === 0) {
                $message = $this->units($remaining) > 0 && $this->units($pending) >= $this->units($remaining)
                    ? 'Your remaining '.$type->name.' credits are currently reserved by pending applications.'
                    : 'Cannot submit this leave application because your available '.$type->name.' balance is 0 days.';

                throw ValidationException::withMessages(['leave_type_id' => $message]);
            }

            throw ValidationException::withMessages([
                'requested_duration' => 'Cannot submit this leave application. You requested '.LeaveDays::withUnit($days).', but only '.LeaveDays::withUnit($available).' are available.',
            ]);
        }

        return $balance;
    }

    public function debitApproval(LeaveRequest $leave, User $actor, float $days): LeaveBalanceTransaction
    {
        $balance = EmployeeLeaveBalance::query()
            ->where('employee_id', $leave->employee_id)
            ->where('leave_type_id', $leave->leave_type_id)
            ->where('policy_period', $leave->start_date->format('Y'))
            ->lockForUpdate()
            ->first();

        if (! $balance) {
            throw ValidationException::withMessages(['chargeable_days' => 'No leave entitlement is configured for this employee, leave type and policy year.']);
        }

        $pending = $this->pendingDays($leave->employee_id, $leave->leave_type_id, $leave->start_date->format('Y'), $leave->id, lock: true);
        $available = max(0, (float) $balance->available_days - $pending);
        if ($this->units($days) > $this->units($available)) {
            throw ValidationException::withMessages(['chargeable_days' => 'Insufficient leave credits after other pending reservations.']);
        }

        $balance->update(['available_days' => $this->days($this->units((float) $balance->available_days) - $this->units($days))]);

        return LeaveBalanceTransaction::create([
            'employee_leave_balance_id' => $balance->id,
            'leave_request_id' => $leave->id,
            'authorized_by_user_id' => $actor->id,
            'transaction_type' => 'approved_debit',
            'amount' => -$days,
            'effective_on' => $leave->start_date,
            'idempotency_key' => 'leave-approval:'.$leave->id,
            'reason' => 'Final Supervisor approval',
            'metadata' => ['policy_period' => $leave->start_date->format('Y')],
        ]);
    }

    public function reverseApproval(LeaveRequest $leave, User $actor, string $reason): ?LeaveBalanceTransaction
    {
        abort_unless($actor->isHr(), 403);
        $debit = LeaveBalanceTransaction::query()->where('leave_request_id', $leave->id)->whereIn('transaction_type', ['approved_debit', 'historical_approved_debit'])->first();
        if (! $debit) {
            return null;
        }

        $existing = LeaveBalanceTransaction::query()->where('reversal_of_transaction_id', $debit->id)->first();
        if ($existing) {
            return $existing;
        }

        $balance = EmployeeLeaveBalance::whereKey($debit->employee_leave_balance_id)->lockForUpdate()->firstOrFail();
        $credit = abs((float) $debit->amount);
        $balance->update(['available_days' => $this->days($this->units((float) $balance->available_days) + $this->units($credit))]);

        return LeaveBalanceTransaction::create([
            'employee_leave_balance_id' => $balance->id,
            'reversal_of_transaction_id' => $debit->id,
            'authorized_by_user_id' => $actor->id,
            'transaction_type' => 'approval_reversal',
            'amount' => $credit,
            'effective_on' => now(config('attendance.timezone'))->toDateString(),
            'idempotency_key' => 'leave-reversal:'.$leave->id,
            'reason' => $reason,
            'metadata' => ['leave_request_id' => $leave->id],
        ]);
    }

    public function adjust(User $actor, Employee $employee, LeaveType $type, string $period, float $amount, string $reason): EmployeeLeaveBalance
    {
        abort_unless($actor->isHr(), 403);
        $balance = EmployeeLeaveBalance::firstOrCreate(
            ['employee_id' => $employee->id, 'leave_type_id' => $type->id, 'policy_period' => $period],
            ['available_days' => 0],
        );
        $balance = EmployeeLeaveBalance::whereKey($balance->id)->lockForUpdate()->firstOrFail();
        $previousDays = (float) $balance->available_days;
        $nextUnits = $this->units((float) $balance->available_days) + $this->units($amount);
        $pendingUnits = $this->units($this->summary($employee, $type, $period)['pending']);
        if ($nextUnits < $pendingUnits) {
            throw ValidationException::withMessages(['amount' => 'The adjustment cannot reduce credits below pending reservations.']);
        }

        $balance->update(['available_days' => $this->days($nextUnits)]);
        $transaction = LeaveBalanceTransaction::create([
            'employee_leave_balance_id' => $balance->id,
            'authorized_by_user_id' => $actor->id,
            'transaction_type' => 'hr_adjustment',
            'amount' => $amount,
            'effective_on' => now(config('attendance.timezone'))->toDateString(),
            'reason' => $reason,
            'metadata' => ['policy_period' => $period],
        ]);
        $balance->setAttribute('adjustment_previous_days', $previousDays);
        $balance->setAttribute('adjustment_transaction_id', $transaction->id);

        return $balance;
    }

    /** @param array{source:string,reference:string,period_start:CarbonInterface|string,period_end:CarbonInterface|string,days:float,event_date?:string|null,event_variant?:string|null,eligibility_status?:string,verification_notes?:string|null} $grant */
    public function grant(User $actor, Employee $employee, LeavePolicy $policy, array $grant): LeaveEntitlement
    {
        abort_unless($actor->isHr(), 403);
        $key = 'entitlement:'.$employee->id.':'.$policy->leave_type_id.':'.$grant['reference'];

        return DB::transaction(function () use ($actor, $employee, $policy, $grant, $key): LeaveEntitlement {
            Employee::whereKey($employee->id)->lockForUpdate()->firstOrFail();
            $existing = LeaveEntitlement::where('idempotency_key', $key)->first();
            if ($existing) {
                return $existing;
            }
            $period = substr((string) $grant['period_start'], 0, 4);
            $balance = EmployeeLeaveBalance::firstOrCreate(
                ['employee_id' => $employee->id, 'leave_type_id' => $policy->leave_type_id, 'policy_period' => $period],
                ['available_days' => 0],
            );
            $balance = EmployeeLeaveBalance::whereKey($balance->id)->lockForUpdate()->firstOrFail();
            $entitlement = LeaveEntitlement::create([
                'employee_id' => $employee->id,
                'leave_type_id' => $policy->leave_type_id,
                'leave_policy_id' => $policy->id,
                'source' => $grant['source'],
                'reference' => $grant['reference'],
                'period_start' => $grant['period_start'],
                'period_end' => $grant['period_end'],
                'event_date' => $grant['event_date'] ?? null,
                'event_variant' => $grant['event_variant'] ?? null,
                'granted_days' => $grant['days'],
                'eligibility_status' => $grant['eligibility_status'] ?? 'not_required',
                'verification_notes' => $grant['verification_notes'] ?? null,
                'verified_by_user_id' => ($grant['eligibility_status'] ?? null) === 'verified' ? $actor->id : null,
                'verified_at' => ($grant['eligibility_status'] ?? null) === 'verified' ? now() : null,
                'created_by_user_id' => $actor->id,
                'idempotency_key' => $key,
            ]);
            $balance->update(['available_days' => $this->days($this->units((float) $balance->available_days) + $this->units($grant['days']))]);
            LeaveBalanceTransaction::create([
                'employee_leave_balance_id' => $balance->id,
                'leave_entitlement_id' => $entitlement->id,
                'authorized_by_user_id' => $actor->id,
                'transaction_type' => $grant['source'],
                'amount' => $grant['days'],
                'effective_on' => $grant['period_start'],
                'idempotency_key' => $key,
                'reason' => $grant['source'] === 'event_grant'
                    ? 'Verified event entitlement granted under the effective policy.'
                    : 'Leave entitlement granted under the effective policy.',
                'metadata' => ['reference' => $grant['reference'], 'policy_id' => $policy->id],
            ]);

            return $entitlement;
        }, 3);
    }

    private function units(float $days): int
    {
        return (int) round($days * 2);
    }

    private function days(int $units): string
    {
        return number_format($units / 2, 2, '.', '');
    }

    private function pendingDays(int $employeeId, int $typeId, string $period, ?int $excludeLeaveRequestId = null, bool $lock = false): float
    {
        $query = LeaveRequest::query()
            ->where('employee_id', $employeeId)
            ->where('leave_type_id', $typeId)
            ->whereIn('status', LeaveRequest::PENDING)
            ->whereYear('start_date', (int) $period)
            ->when($excludeLeaveRequestId, fn ($query, $id) => $query->whereKeyNot($id));

        if ($lock) {
            $query->lockForUpdate();
        }

        return $query->get(['reserved_duration', 'requested_duration'])
            ->sum(fn (LeaveRequest $leave): float => (float) ($leave->reserved_duration ?? $leave->requested_duration));
    }
}
