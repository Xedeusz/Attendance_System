<?php

namespace App\Http\Controllers\HR;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\EmployeeLeaveBalance;
use App\Models\LeavePolicy;
use App\Models\LeaveType;
use App\Services\AuditService;
use App\Services\LeaveBalanceService;
use App\Services\WorkflowNotices;
use App\Support\LeaveDays;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class LeaveCreditController extends Controller
{
    public function index(Request $request, LeaveBalanceService $balances): View
    {
        $data = $request->validate([
            'employee_id' => ['nullable', 'integer', 'exists:employees,id'],
            'leave_type_id' => ['nullable', 'integer', 'exists:leave_types,id'],
            'year' => ['nullable', 'regex:/^20[0-9]{2}$/'],
        ]);
        $year = $data['year'] ?? now(config('attendance.timezone'))->format('Y');
        $records = EmployeeLeaveBalance::query()
            ->with(['employee.user', 'type', 'transactions.authorizer', 'transactions.entitlement'])
            ->where('policy_period', $year)
            ->when($data['employee_id'] ?? null, fn ($query, $id) => $query->where('employee_id', $id))
            ->when($data['leave_type_id'] ?? null, fn ($query, $id) => $query->where('leave_type_id', $id))
            ->orderBy('employee_id')
            ->orderBy('leave_type_id')
            ->paginate(15)
            ->withQueryString();
        $records->through(function (EmployeeLeaveBalance $balance) use ($balances): EmployeeLeaveBalance {
            $balance->setAttribute('credit_summary', $balances->summary($balance->employee_id, $balance->leave_type_id, $balance->policy_period));

            return $balance;
        });

        return view('hr.leave.credits', [
            'balances' => $records,
            'employees' => Employee::with('user')->where('employment_status', 'active')->orderBy('last_name')->orderBy('id')->get(),
            'types' => LeaveType::where('affects_balance', true)->orderBy('name')->get(),
            'eventPolicies' => LeavePolicy::with('type')->where('allocation_basis', 'event')->where('is_active', true)->orderBy('leave_type_id')->get(),
            'policies' => LeavePolicy::with('type')->orderByDesc('effective_from')->orderBy('leave_type_id')->get(),
            'year' => $year,
        ]);
    }

    public function storePolicy(Request $request, AuditService $audit): RedirectResponse
    {
        $data = $request->validate([
            'leave_type_id' => ['required', 'integer', 'exists:leave_types,id'],
            'effective_from' => ['required', 'date_format:Y-m-d'],
            'allocation_basis' => ['required', Rule::in(['annual', 'event'])],
            'default_days' => ['required', 'numeric', 'min:0.5', 'max:366', 'multiple_of:0.5'],
            'day_basis' => ['required', Rule::in(['working_days', 'calendar_days'])],
            'carryover_limit' => ['nullable', 'numeric', 'min:0', 'max:366', 'multiple_of:0.5'],
            'proration_rule' => ['required', Rule::in(['none', 'monthly'])],
        ]);

        $policy = DB::transaction(function () use ($request, $data): LeavePolicy {
            $nextEffectiveFrom = LeavePolicy::query()
                ->where('leave_type_id', $data['leave_type_id'])
                ->whereDate('effective_from', '>', $data['effective_from'])
                ->orderBy('effective_from')
                ->value('effective_from');
            LeavePolicy::query()
                ->where('leave_type_id', $data['leave_type_id'])
                ->where('is_active', true)
                ->whereDate('effective_from', '<', $data['effective_from'])
                ->where(fn ($query) => $query->whereNull('effective_until')->orWhereDate('effective_until', '>=', $data['effective_from']))
                ->update(['effective_until' => CarbonImmutable::parse($data['effective_from'])->subDay()->toDateString()]);

            return LeavePolicy::updateOrCreate(
                ['leave_type_id' => $data['leave_type_id'], 'effective_from' => $data['effective_from']],
                $data + [
                    'effective_until' => $nextEffectiveFrom ? CarbonImmutable::parse($nextEffectiveFrom)->subDay()->toDateString() : null,
                    'is_active' => true,
                    'created_by_user_id' => $request->user()->id,
                ],
            );
        }, 3);
        $audit->record('leave.policy_saved', $policy, ['effective_from' => $policy->effective_from->toDateString(), 'default_days' => $policy->default_days]);

        return back()->with('status', 'Effective-dated leave policy saved. Existing historical ledger entries were not changed.');
    }

    public function grantEvent(Request $request, LeaveBalanceService $balances, AuditService $audit, WorkflowNotices $notices): RedirectResponse
    {
        $data = $request->validate([
            'employee_id' => ['required', 'integer', 'exists:employees,id'],
            'leave_policy_id' => ['required', 'integer', 'exists:leave_policies,id'],
            'event_date' => ['required', 'date_format:Y-m-d'],
            'event_variant' => ['required', Rule::in(['standard', 'solo_parent', 'miscarriage_or_emergency_termination'])],
            'verification_notes' => ['required', 'string', 'min:10', 'max:2000'],
            'confirmation' => ['accepted'],
        ]);
        $employee = Employee::with('user')->whereKey($data['employee_id'])->where('employment_status', 'active')->firstOrFail();
        $policy = LeavePolicy::with('type')->whereKey($data['leave_policy_id'])->where('allocation_basis', 'event')->where('is_active', true)->firstOrFail();
        abort_unless($policy->effective_from->toDateString() <= $data['event_date'] && (! $policy->effective_until || $policy->effective_until->toDateString() >= $data['event_date']), 422, 'No effective event policy covers this date.');
        $rules = $policy->eligibility_rules ?? [];
        $days = match ($data['event_variant']) {
            'solo_parent' => (float) ($rules['solo_parent_days'] ?? $policy->default_days),
            'miscarriage_or_emergency_termination' => (float) ($rules['miscarriage_or_emergency_termination_days'] ?? $policy->default_days),
            default => (float) ($rules['standard_days'] ?? $policy->default_days),
        };
        $year = substr($data['event_date'], 0, 4);
        $entitlement = $balances->grant($request->user(), $employee, $policy, [
            'source' => 'event_grant',
            'reference' => 'event-'.$data['event_date'],
            'period_start' => $year.'-01-01',
            'period_end' => $year.'-12-31',
            'days' => $days,
            'event_date' => $data['event_date'],
            'event_variant' => $data['event_variant'],
            'eligibility_status' => 'verified',
            'verification_notes' => $data['verification_notes'],
        ]);
        $audit->record('leave.event_entitlement_granted', $entitlement, ['employee_id' => $employee->id, 'days' => $days, 'variant' => $data['event_variant']]);
        $notices->send($employee->user, 'leave', $policy->type->name.' entitlement of '.LeaveDays::withUnit($days).' was verified and granted by HR.', route('employee.leave.index'), $entitlement->id);

        return back()->with('status', 'Verified event entitlement granted and recorded in the ledger.');
    }

    public function export(Request $request, LeaveBalanceService $balances): StreamedResponse
    {
        $data = $request->validate([
            'employee_id' => ['nullable', 'integer', 'exists:employees,id'],
            'leave_type_id' => ['nullable', 'integer', 'exists:leave_types,id'],
            'year' => ['nullable', 'regex:/^20[0-9]{2}$/'],
        ]);
        $year = $data['year'] ?? now(config('attendance.timezone'))->format('Y');
        $query = EmployeeLeaveBalance::query()
            ->with(['employee.department', 'employee.currentAssignment.branch', 'type'])
            ->where('policy_period', $year)
            ->when($data['employee_id'] ?? null, fn ($builder, $id) => $builder->where('employee_id', $id))
            ->when($data['leave_type_id'] ?? null, fn ($builder, $id) => $builder->where('leave_type_id', $id));

        return response()->streamDownload(function () use ($balances, $query): void {
            $output = fopen('php://output', 'wb');
            fputcsv($output, ['Employee Number', 'Employee', 'Branch', 'Department', 'Leave Type', 'Allocation Period', 'Event Reference', 'Eligibility Status', 'Total Entitlement', 'Approved Days', 'Pending Days', 'Remaining Days', 'Available for Request']);
            foreach ($query->lazyById(200) as $balance) {
                $summary = $balances->summary($balance->employee_id, $balance->leave_type_id, $balance->policy_period);
                fputcsv($output, array_map($this->csvValue(...), [
                    $balance->employee->employee_number,
                    $balance->employee->full_name,
                    $balance->employee->currentAssignment?->branch?->branch_name,
                    $balance->employee->department?->department_name,
                    $balance->type->name,
                    $balance->policy_period,
                    $summary['event_reference'],
                    $summary['eligibility_status'],
                    LeaveDays::format($summary['entitled']),
                    LeaveDays::format($summary['approved']),
                    LeaveDays::format($summary['pending']),
                    LeaveDays::format($summary['remaining']),
                    LeaveDays::format($summary['available']),
                ]));
            }
            fclose($output);
        }, 'leave-credit-report-'.$year.'.csv', ['Content-Type' => 'text/csv; charset=UTF-8', 'X-Content-Type-Options' => 'nosniff']);
    }

    private function csvValue(mixed $value): mixed
    {
        if (! is_string($value) || $value === '') {
            return $value;
        }

        return preg_match('/^[=+\-@\t\r]/', $value) === 1 ? "'".$value : $value;
    }
}
