<?php

namespace App\Services;

use App\Models\AccountInvitation;
use App\Models\AttendanceCorrectionRequest;
use App\Models\AttendanceRecord;
use App\Models\Branch;
use App\Models\Employee;
use App\Models\LeaveRequest;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Generator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class AttendanceReportingService
{
    public function __construct(
        private AttendancePolicyResolver $policies,
        private AttendanceComputationService $computations,
        private WorkDuration $duration,
    ) {}

    private function employees(array $filters, CarbonImmutable $from, CarbonImmutable $to): Builder
    {
        return Employee::query()
            ->where('date_hired', '<', $to->addDay()->toDateString())
            ->when($filters['department_id'] ?? null, fn (Builder $query, $department) => $query->where('department_id', $department))
            ->when($filters['branch_id'] ?? null, fn (Builder $query, $branch) => $query->where(fn (Builder $matches) => $matches
                ->whereHas('assignments', fn (Builder $assignment) => $assignment->where('branch_id', $branch)->where('is_primary', true)
                    ->where('effective_from', '<', $to->addDay()->utc())
                    ->where(fn (Builder $end) => $end->whereNull('effective_until')->orWhere('effective_until', '>', $from->utc())))
                ->orWhereIn('id', AttendanceRecord::query()->select('employee_id')->where('branch_id', $branch)
                    ->where('work_date', '>=', $from->toDateString())->where('work_date', '<', $to->addDay()->toDateString()))
                ->orWhereIn('id', AttendanceCorrectionRequest::query()->select('employee_id')->where('branch_id', $branch)
                    ->whereNull('attendance_record_id')->whereIn('status', ['approved', 'pending'])
                    ->where('requested_date', '>=', $from->toDateString())->where('requested_date', '<', $to->addDay()->toDateString()))));
    }

    public function employeeOptions(array $filters): Collection
    {
        [$from, $to] = $this->period($filters);

        return $this->employees($filters, $from, $to)
            ->orderBy('last_name')->orderBy('first_name')->orderBy('id')
            ->get(['id', 'employee_number', 'first_name', 'middle_name', 'last_name', 'suffix']);
    }

    public function report(array $filters, int $page): array
    {
        $summary = ['Scheduled Workdays' => 0, 'Present Days' => 0, 'Late Arrivals' => 0, 'Total Work Hours' => 0, 'Absences' => 0, 'Approved Leave Days' => 0];
        $items = [];
        $total = 0;
        $perPage = 25;
        $offset = ($page - 1) * $perPage;

        foreach ($this->rows($filters) as $row) {
            $summary['Scheduled Workdays'] += (int) $row['scheduled'];
            $summary['Present Days'] += (int) ($row['scheduled'] && $row['in'] !== null);
            $summary['Late Arrivals'] += (int) ($row['late'] > 0);
            $summary['Total Work Hours'] += $row['minutes'] ?? 0;
            $summary['Absences'] += (int) $row['absent'];
            $summary['Approved Leave Days'] += $row['leave_days'];
            if ($total >= $offset && count($items) < $perPage) {
                $items[] = $row;
            }
            $total++;
        }

        return [$summary, new LengthAwarePaginator($items, $total, $perPage, $page, [
            'path' => route('hr.attendance-reports.index'), 'query' => $filters,
        ])];
    }

    /** @return Generator<int, array> */
    public function rows(array $filters): Generator
    {
        [$from, $to] = $this->period($filters);
        $descending = ($filters['direction'] ?? null) === 'desc';
        $now = CarbonImmutable::now($from->timezone);
        $employees = $this->employees($filters, $from, $to)
            ->when($filters['employee_id'] ?? null, fn (Builder $query, $employee) => $query->whereKey($employee));

        $employeeCount = $employees->count();
        if ($employeeCount * ((int) $from->diffInDays($to) + 1) > 100_000) {
            throw ValidationException::withMessages(['to' => 'This report would evaluate more than 100,000 employee-days. Narrow the dates, department, or employee.'])
                ->redirectTo(route('hr.attendance-reports.index'));
        }

        $windowDays = max(1, min(7, intdiv(7_000, max(1, $employeeCount))));
        $branches = Branch::pluck('branch_name', 'id');
        for ($cursor = $descending ? $to : $from; $cursor->betweenIncluded($from, $to); $cursor = $descending ? $start->subDay() : $end->addDay()) {
            $start = $descending ? max($from, $cursor->subDays($windowDays - 1)) : $cursor;
            $end = $descending ? $cursor : min($to, $cursor->addDays($windowDays - 1));
            $dates = [];
            for ($date = $start; $date->lte($end); $date = $date->addDay()) {
                $dates[$date->toDateString()] = [];
            }

            foreach ((clone $employees)->with(['department:id,department_name', 'user:id,account_status'])
                ->lazyById(200)->chunk(200) as $batch) {
                $batch = (new Employee)->newCollection($batch->all());
                $policies = $this->policies->forPeriod($batch, $start, $end);
                $ids = $batch->modelKeys();
                $range = [$start->toDateString(), $end->addDay()->toDateString()];
                $records = AttendanceRecord::with(['branch', 'computation'])->whereIn('employee_id', $ids)
                    ->where('work_date', '>=', $range[0])->where('work_date', '<', $range[1])->orderBy('id')->get();
                $workDates = $records->mapWithKeys(fn (AttendanceRecord $record) => [$record->id => $record->work_date->toDateString()]);
                $records = $records->groupBy(['employee_id', fn (AttendanceRecord $record) => $record->work_date->toDateString()]);
                $corrections = AttendanceCorrectionRequest::with(['branch', 'computation'])->whereIn('employee_id', $ids)
                    ->whereIn('status', ['approved', 'pending'])
                    ->where(fn (Builder $query) => $query->whereIn('attendance_record_id', $workDates->keys())
                        ->orWhere(fn (Builder $manual) => $manual->whereNull('attendance_record_id')->where('requested_date', '>=', $range[0])->where('requested_date', '<', $range[1])))
                    ->orderBy('id')->get()->groupBy(['employee_id', fn (AttendanceCorrectionRequest $correction) => $workDates->get($correction->attendance_record_id) ?? $correction->requested_date->toDateString()]);
                $pendingLeave = LeaveRequest::whereIn('employee_id', $ids)->whereIn('status', LeaveRequest::PENDING)
                    ->where('start_date', '<', $range[1])->where('end_date', '>=', $range[0])
                    ->get(['employee_id', 'start_date', 'end_date'])->groupBy('employee_id');
                $activations = AccountInvitation::whereIn('user_id', $batch->pluck('user_id'))->whereNotNull('used_at')
                    ->groupBy('user_id')->selectRaw('user_id, MIN(used_at) as activated')->pluck('activated', 'user_id');

                foreach ($batch as $employee) {
                    $activation = $activations->get($employee->user_id);
                    $eligibleFrom = max($employee->date_hired->toDateString(), $activation
                        ? CarbonImmutable::parse($activation, config('app.timezone'))->setTimezone($from->timezone)->toDateString()
                        : '');
                    foreach ($policies[$employee->id] as $date => $policy) {
                        $sessions = $records->get($employee->id)?->get($date) ?? collect();
                        $requests = $corrections->get($employee->id)?->get($date) ?? collect();
                        $branchId = $policy['branch_id'] ?? $sessions->first()?->branch_id ?? $requests->first()?->branch_id;
                        if (isset($filters['branch_id']) && (int) $filters['branch_id'] !== $branchId) {
                            continue;
                        }

                        $eligible = $date >= $eligibleFrom && $employee->employment_status === 'active' && $employee->user?->account_status !== 'pending_activation';
                        if (! $eligible && $sessions->isEmpty() && $requests->isEmpty() && $policy['leave_ids'] === []) {
                            continue;
                        }

                        $pending = $pendingLeave->get($employee->id, collect())->contains(
                            fn (LeaveRequest $leave): bool => $leave->start_date->toDateString() <= $date && $leave->end_date->toDateString() >= $date
                        );
                        $row = $this->row($employee, $policy, $sessions, $requests, $branches->get($branchId), $eligible, $pending, $now);
                        if ($windowDays === 1) {
                            yield $row;
                        } else {
                            $dates[$date][] = $row;
                        }
                    }
                }
            }

            foreach ($descending ? array_reverse($dates) : $dates as $rows) {
                yield from $rows;
            }
        }
    }

    private function row(Employee $employee, array $policy, Collection $sessions, Collection $corrections, ?string $branch, bool $eligible, bool $pendingLeave, CarbonImmutable $now): array
    {
        $approved = $corrections->where('status', 'approved');
        $manual = $approved->whereNull('attendance_record_id');
        $calculation = $this->computations->day(collect([...$sessions, ...$manual]));
        $timestamps = $sessions->map(fn (AttendanceRecord $record): array => [
            $record->effective_time_in_at ?? $record->time_in_at,
            $record->effective_time_out_at ?? $record->time_out_at,
        ])->concat($manual->map(fn (AttendanceCorrectionRequest $request): array => [$request->requested_time_in_at, $request->requested_time_out_at]));
        $in = $timestamps->pluck(0)->filter()->min();
        $out = $timestamps->pluck(1)->filter()->max();
        $schedulePolicy = ($calculation?->policy['schedule'] ?? null) ? $calculation->policy : $policy;
        $schedule = $schedulePolicy['schedule'];
        $shift = $schedule ? $this->duration->shift($policy['date'], $schedule, $policy['timezone'], $schedulePolicy['adjusted_end']) : null;
        $holiday = collect($policy['holidays'])->contains(fn (array $declaration): bool => ! $declaration['work_expected']);
        $obligation = $this->policies->obligationWithoutLeave($policy);
        $scheduled = $obligation === 'work';
        $leaveDays = $scheduled ? ($policy['full_leave'] ? 1 : ($policy['partial_leave'] ? 0.5 : 0)) : 0;
        $pendingReview = $sessions->contains('review_status', 'pending');
        $incomplete = ($calculation?->incomplete ?? false) || ($out && ! $in);
        $absent = $eligible && $policy['obligation'] === 'work' && ! $in && ! $out && ! $pendingReview && $shift && $now->gte($shift['end']);
        $status = array_values(array_filter([
            $in ? 'Present' : null,
            $calculation?->late_minutes > 0 ? 'Late' : null,
            $calculation?->undertime_minutes > 0 ? 'Undertime' : null,
            $incomplete ? 'Incomplete attendance' : null,
            $absent ? 'Absent' : null,
            $holiday ? 'Holiday' : null,
            $obligation === 'not_scheduled' && ! $holiday ? 'Rest day' : null,
            $policy['obligation'] === 'excused' ? 'Authorized absence' : null,
            $policy['obligation'] === 'no_schedule' ? 'No work schedule configured' : null,
            $pendingReview ? 'Pending HR review' : null,
            $corrections->contains('status', 'pending') ? 'Pending correction' : null,
            $approved->isNotEmpty() || $sessions->contains(fn (AttendanceRecord $record): bool => $record->correction_version > 0) ? 'Approved correction' : null,
        ]));
        $leave = match (true) {
            $policy['full_leave'] => 'Approved leave',
            $policy['partial_leave'] !== null => 'Approved leave ('.strtoupper($policy['partial_leave']).')',
            $pendingLeave => 'Pending leave',
            default => '',
        };
        $timestamp = fn (?CarbonInterface $time): ?string => $time?->copy()->setTimezone($policy['timezone'])->format('Y-m-d H:i:s');

        return [
            'date' => $policy['date'], 'employee_id' => $employee->id, 'employee_number' => $employee->employee_number,
            'employee' => $employee->full_name, 'branch' => $branch, 'department' => $employee->department?->department_name,
            'schedule' => $shift ? $schedule['name'].' · '.$shift['start']->format('H:i').'–'.$shift['end']->format('H:i')
                .($shift['end']->toDateString() !== $policy['date'] ? ' (+1 day)' : '') : null,
            'in' => $timestamp($in), 'out' => $timestamp($out), 'minutes' => $calculation?->net_minutes,
            'late' => $calculation?->late_minutes, 'undertime' => $calculation?->undertime_minutes,
            'status' => implode('; ', $status ?: [$leave ?: ($eligible ? 'Scheduled work' : 'Recorded history')]),
            'leave' => $leave, 'scheduled' => $scheduled && ($eligible || $in !== null || $leaveDays > 0),
            'absent' => $absent, 'leave_days' => $leaveDays,
        ];
    }

    /** @return array{CarbonImmutable, CarbonImmutable} */
    private function period(array $filters): array
    {
        $timezone = config('attendance.timezone');

        return [CarbonImmutable::parse($filters['from'], $timezone), CarbonImmutable::parse($filters['to'], $timezone)];
    }
}
