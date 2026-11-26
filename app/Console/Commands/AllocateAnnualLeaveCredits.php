<?php

namespace App\Console\Commands;

use App\Models\Employee;
use App\Models\EmployeeLeaveBalance;
use App\Models\LeaveEntitlement;
use App\Models\LeavePolicy;
use App\Models\Role;
use App\Models\User;
use App\Services\LeaveBalanceService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('leave:allocate-annual {year?} {--dry-run} {--actor= : HR user ID recorded as the authorizer}')]
#[Description('Allocate effective annual leave entitlements to active employees without duplicates')]
class AllocateAnnualLeaveCredits extends Command
{
    public function handle(LeaveBalanceService $balances): int
    {
        $year = (int) ($this->argument('year') ?: now(config('attendance.timezone'))->year);
        if ($year < 2000 || $year > 2100) {
            $this->error('Year must be between 2000 and 2100.');

            return self::FAILURE;
        }

        $actor = User::query()->whereKey($this->option('actor'))->where('role_id', Role::HR)->first()
            ?? User::query()->where('role_id', Role::HR)->orderBy('id')->first();
        if (! $actor && ! $this->option('dry-run')) {
            $this->error('Create an HR account or pass --actor=<HR user ID> so allocations have an audit authorizer.');

            return self::FAILURE;
        }

        $from = sprintf('%d-01-01', $year);
        $until = sprintf('%d-12-31', $year);
        $policies = LeavePolicy::query()
            ->with('type')
            ->where('allocation_basis', 'annual')
            ->where('is_active', true)
            ->whereDate('effective_from', '<=', $until)
            ->where(fn ($query) => $query->whereNull('effective_until')->orWhereDate('effective_until', '>=', $from))
            ->orderBy('leave_type_id')
            ->orderByDesc('effective_from')
            ->get()
            ->unique('leave_type_id');

        $allocated = 0;
        $skipped = 0;
        Employee::query()->where('employment_status', 'active')->whereDate('date_hired', '<=', $until)->orderBy('id')->chunkById(100, function ($employees) use ($actor, $balances, $from, $until, $policies, $year, &$allocated, &$skipped): void {
            foreach ($employees as $employee) {
                foreach ($policies as $policy) {
                    $reference = 'annual-'.$year;
                    $key = 'entitlement:'.$employee->id.':'.$policy->leave_type_id.':'.$reference;
                    if (LeaveEntitlement::where('idempotency_key', $key)->exists()) {
                        $skipped++;

                        continue;
                    }
                    if ($this->option('dry-run')) {
                        $allocated++;

                        continue;
                    }
                    $days = (float) $policy->default_days;
                    if ($policy->proration_rule === 'monthly' && $employee->date_hired?->year === $year) {
                        $remainingMonths = 13 - $employee->date_hired->month;
                        $days = round(($days * $remainingMonths / 12) * 2) / 2;
                    }
                    if ($policy->carryover_limit !== null && (float) $policy->carryover_limit > 0) {
                        $previous = (float) EmployeeLeaveBalance::query()
                            ->where('employee_id', $employee->id)
                            ->where('leave_type_id', $policy->leave_type_id)
                            ->where('policy_period', (string) ($year - 1))
                            ->value('available_days');
                        $days += min($previous, (float) $policy->carryover_limit);
                    }
                    $balances->grant($actor, $employee, $policy, [
                        'source' => 'annual_allocation',
                        'reference' => $reference,
                        'period_start' => $from,
                        'period_end' => $until,
                        'days' => $days,
                    ]);
                    $allocated++;
                }
            }
        });

        $mode = $this->option('dry-run') ? 'would be allocated' : 'allocated';
        $this->info("{$allocated} entitlements {$mode}; {$skipped} duplicates skipped.");

        return self::SUCCESS;
    }
}
