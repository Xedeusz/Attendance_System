<?php

namespace App\Console\Commands;

use App\Models\EmployeeLeaveBalance;
use App\Models\LeaveBalanceTransaction;
use App\Models\LeaveEntitlement;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\Role;
use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

#[Signature('leave:reconcile-credits {--apply : Write opening entitlements and ledger entries} {--actor= : HR user ID recorded as the authorizer}')]
#[Description('Preview or apply opening entries and historical approved-leave debits')]
class ReconcileLeaveCredits extends Command
{
    public function handle(): int
    {
        $actor = User::query()->whereKey($this->option('actor'))->where('role_id', Role::HR)->first()
            ?? User::query()->where('role_id', Role::HR)->orderBy('id')->first();
        if ($this->option('apply') && ! $actor) {
            $this->error('Create an HR account or pass --actor=<HR user ID> before applying reconciliation.');

            return self::FAILURE;
        }

        $candidates = EmployeeLeaveBalance::query()
            ->with(['employee', 'type'])
            ->whereDoesntHave('transactions', fn ($query) => $query->whereIn('transaction_type', ['annual_allocation', 'event_grant', 'opening_balance', 'manual_grant']))
            ->orderBy('id')
            ->get();

        $this->table(['Balance', 'Employee', 'Type', 'Period', 'Opening days'], $candidates->map(fn (EmployeeLeaveBalance $balance): array => [
            $balance->id,
            $balance->employee->full_name,
            $balance->type->name,
            $balance->policy_period,
            $balance->available_days,
        ]));

        $canonicalTypes = LeaveType::query()
            ->whereIn('code', ['VACATION', 'SICK', 'BIRTHDAY'])
            ->get()
            ->keyBy('code');
        $historicalApprovals = LeaveRequest::query()
            ->with(['employee', 'type'])
            ->where('status', 'approved')
            ->whereDoesntHave('balanceTransaction')
            ->orderBy('id')
            ->get()
            ->map(function (LeaveRequest $leave) use ($canonicalTypes): ?array {
                $code = $this->canonicalCode($leave);
                $canonicalType = $code ? $canonicalTypes->get($code) : null;
                if (! $canonicalType) {
                    return null;
                }

                $balance = EmployeeLeaveBalance::query()
                    ->where('employee_id', $leave->employee_id)
                    ->where('leave_type_id', $canonicalType->id)
                    ->where('policy_period', $leave->start_date->format('Y'))
                    ->first();
                if (! $balance) {
                    return null;
                }

                $savedCharge = (float) ($leave->charged_duration ?? 0);

                return [
                    'leave' => $leave,
                    'balance' => $balance,
                    'canonical_type' => $canonicalType,
                    'days' => $savedCharge > 0 ? $savedCharge : (float) $leave->requested_duration,
                    'used_requested_duration' => $savedCharge <= 0,
                ];
            })
            ->filter()
            ->values();

        $this->table(['Leave', 'Employee', 'Legacy type', 'Canonical type', 'Period', 'Debit days', 'Charge source'], $historicalApprovals->map(fn (array $candidate): array => [
            $candidate['leave']->id,
            $candidate['leave']->employee->full_name,
            $candidate['leave']->type->name,
            $candidate['canonical_type']->name,
            $candidate['leave']->start_date->format('Y'),
            number_format($candidate['days'], 2),
            $candidate['used_requested_duration'] ? 'requested duration fallback' : 'saved charged duration',
        ]));

        if (! $this->option('apply')) {
            $this->info($candidates->count().' legacy balances and '.$historicalApprovals->count().' historical approvals found. Re-run with --apply after reviewing this preview.');

            return self::SUCCESS;
        }

        foreach ($candidates as $candidate) {
            DB::transaction(function () use ($actor, $candidate): void {
                $balance = EmployeeLeaveBalance::whereKey($candidate->id)->lockForUpdate()->firstOrFail();
                $reference = 'opening-'.$balance->policy_period;
                $key = 'entitlement:'.$balance->employee_id.':'.$balance->leave_type_id.':'.$reference;
                if (LeaveEntitlement::where('idempotency_key', $key)->exists()) {
                    return;
                }
                $approved = max(0, -(float) $balance->transactions()->whereIn('transaction_type', ['approved_debit', 'historical_approved_debit', 'approval_reversal'])->sum('amount'));
                $openingDays = (float) $balance->available_days + $approved;
                $entitlement = LeaveEntitlement::create([
                    'employee_id' => $balance->employee_id,
                    'leave_type_id' => $balance->leave_type_id,
                    'source' => 'opening_balance',
                    'reference' => $reference,
                    'period_start' => $balance->policy_period.'-01-01',
                    'period_end' => $balance->policy_period.'-12-31',
                    'granted_days' => $openingDays,
                    'eligibility_status' => 'not_required',
                    'verification_notes' => 'Opening entitlement reconstructed from the legacy balance projection and approved debits.',
                    'created_by_user_id' => $actor->id,
                    'idempotency_key' => $key,
                ]);
                LeaveBalanceTransaction::create([
                    'employee_leave_balance_id' => $balance->id,
                    'leave_entitlement_id' => $entitlement->id,
                    'authorized_by_user_id' => $actor->id,
                    'transaction_type' => 'opening_balance',
                    'amount' => $openingDays,
                    'effective_on' => $balance->policy_period.'-01-01',
                    'idempotency_key' => $key,
                    'reason' => 'Historical opening entitlement reconciliation.',
                    'metadata' => ['reconciled_balance_id' => $balance->id, 'approved_days' => $approved],
                ]);
            }, 3);
        }

        $reconciledApprovals = 0;
        $skippedApprovals = 0;
        foreach ($historicalApprovals as $candidate) {
            $applied = DB::transaction(function () use ($actor, $candidate): bool {
                $leave = LeaveRequest::whereKey($candidate['leave']->id)->lockForUpdate()->firstOrFail();
                if ($leave->status !== 'approved' || LeaveBalanceTransaction::where('leave_request_id', $leave->id)->exists()) {
                    return false;
                }

                $balance = EmployeeLeaveBalance::whereKey($candidate['balance']->id)->lockForUpdate()->firstOrFail();
                $remainingUnits = (int) round((float) $balance->available_days * 2) - (int) round($candidate['days'] * 2);
                if ($remainingUnits < 0) {
                    return false;
                }

                $balance->update(['available_days' => number_format($remainingUnits / 2, 2, '.', '')]);
                LeaveBalanceTransaction::create([
                    'employee_leave_balance_id' => $balance->id,
                    'leave_request_id' => $leave->id,
                    'authorized_by_user_id' => $actor->id,
                    'transaction_type' => 'historical_approved_debit',
                    'amount' => -$candidate['days'],
                    'effective_on' => $leave->start_date,
                    'idempotency_key' => 'historical-approval:'.$leave->id,
                    'reason' => 'Historical approved leave reconciliation.',
                    'metadata' => [
                        'legacy_leave_type_id' => $leave->leave_type_id,
                        'canonical_leave_type_id' => $candidate['canonical_type']->id,
                        'used_requested_duration' => $candidate['used_requested_duration'],
                    ],
                ]);

                return true;
            }, 3);

            $applied ? $reconciledApprovals++ : $skippedApprovals++;
        }

        $this->info($candidates->count().' opening entitlements and '.$reconciledApprovals.' historical approvals reconciled; '.$skippedApprovals.' approvals skipped for review.');

        return self::SUCCESS;
    }

    private function canonicalCode(LeaveRequest $leave): ?string
    {
        $code = mb_strtoupper($leave->type->code);
        if (in_array($code, ['VACATION', 'SICK', 'BIRTHDAY'], true)) {
            return $code;
        }

        $name = mb_strtolower((string) ($leave->type_snapshot['name'] ?? $leave->type->name));

        return match (true) {
            str_contains($name, 'vacation') => 'VACATION',
            str_contains($name, 'sick') => 'SICK',
            str_contains($name, 'birthday') => 'BIRTHDAY',
            default => null,
        };
    }
}
