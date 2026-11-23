<?php

namespace App\Services;

use App\Models\AttendanceHoliday;
use App\Models\Branch;
use App\Models\Employee;
use App\Models\EmployeeBranchAssignment;
use App\Models\LeaveRequest;
use App\Models\WorkSchedule;
use App\Models\WorkScheduleException;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;

class AttendancePolicyResolver
{
    public const OBLIGATIONS = [
        'work' => 'Scheduled work', 'not_scheduled' => 'No work scheduled', 'approved_leave' => 'Approved leave',
        'excused' => 'Authorized absence', 'no_schedule' => 'No work schedule configured',
    ];

    private const SCHEDULE_PRIORITY = 'CASE WHEN employee_id IS NOT NULL THEN 1 WHEN department_id IS NOT NULL THEN 2 WHEN branch_id IS NOT NULL THEN 3 ELSE 4 END';

    public function branchFor(Employee $employee, string $date): ?Branch
    {
        $day = CarbonImmutable::parse($date, config('attendance.timezone'));

        return $employee->assignments()->with('branch')->where('is_primary', true)
            ->where('effective_from', '<', $day->addDay()->utc())
            ->where(fn (Builder $query) => $query->whereNull('effective_until')->orWhere('effective_until', '>', $day->utc()))
            ->orderByDesc('effective_from')->orderByDesc('id')->first()?->branch;
    }

    public static function location(?Branch $branch): ?string
    {
        if (! $branch?->province) {
            return null;
        }

        return mb_strtolower(trim((string) $branch->city).' | '.trim($branch->province));
    }

    /** @return array<string,string> */
    public static function locationOptions(Collection $branches): array
    {
        $options = [];
        foreach ($branches as $branch) {
            $location = self::location($branch);
            if ($location) {
                $options['province:'.mb_strtolower(trim($branch->province))] = 'Province: '.$branch->province;
                if ($branch->city) {
                    $options[$location] = $branch->city.', '.$branch->province;
                }
            }
        }
        asort($options, SORT_NATURAL | SORT_FLAG_CASE);

        return $options;
    }

    public function schedule(Employee $employee, string $date, ?int $branchId): ?WorkSchedule
    {
        return WorkSchedule::effectiveOn($date)->where(fn (Builder $query) => $query
            ->where('employee_id', $employee->id)
            ->orWhere(fn (Builder $department) => $department->whereNull('employee_id')->where('department_id', $employee->department_id)->whereNotNull('department_id'))
            ->when($branchId, fn (Builder $scopes) => $scopes->orWhere(fn (Builder $branch) => $branch->whereNull('employee_id')->whereNull('department_id')->where('branch_id', $branchId)))
            ->orWhere(fn (Builder $default) => $default->whereNull('employee_id')->whereNull('department_id')->whereNull('branch_id')))
            ->orderByRaw(self::SCHEDULE_PRIORITY)
            ->first();
    }

    public function holidaysFor(string $date, ?int $branchId, ?string $location): Collection
    {
        return AttendanceHoliday::with('branches')->where('status', 'active')->whereDate('holiday_date', $date)
            ->orderBy('id')->get()->filter(fn (AttendanceHoliday $holiday) => $holiday->appliesTo($branchId, $location))->values();
    }

    public function resolve(Employee $employee, string $date, ?Branch $branch, ?array $pinned = null): array
    {
        $branchId = $pinned ? $pinned['branch_id'] : $branch?->id;
        $location = $pinned ? $pinned['location'] : self::location($branch);
        $base = $pinned['base_schedule'] ?? $this->schedule($employee, $date, $branchId)?->snapshot();
        $holidays = $this->holidaysFor($date, $branchId, $location)->map->snapshot()->all();
        $exception = WorkScheduleException::with('workSchedule')->where('employee_id', $employee->id)
            ->whereDate('work_date', $date)->where('active', true)->first();
        $leaves = LeaveRequest::where('employee_id', $employee->id)->where('status', 'approved')
            ->whereDate('start_date', '<=', $date)->where('end_date', '>=', $date)->orderBy('id')->get(['id', 'half_day']);

        return $this->assemble($date, $branchId, $location, $base, $holidays, $exception, $leaves);
    }

    public function forPeriod(EloquentCollection $employees, CarbonImmutable $from, CarbonImmutable $until): array
    {
        $employeeIds = $employees->modelKeys();
        $start = $from->toDateString();
        $end = $until->toDateString();
        $assignments = EmployeeBranchAssignment::with('branch')->whereIn('employee_id', $employeeIds)->where('is_primary', true)
            ->where('effective_from', '<', $until->addDay()->utc())
            ->where(fn (Builder $query) => $query->whereNull('effective_until')->orWhere('effective_until', '>', $from->utc()))
            ->orderByDesc('effective_from')->orderByDesc('id')->get()->groupBy('employee_id');
        $schedules = WorkSchedule::where('active', true)->whereDate('effective_from', '<=', $end)
            ->where(fn (Builder $query) => $query->whereNull('effective_until')->orWhere('effective_until', '>=', $start))
            ->where(fn (Builder $scope) => $scope->whereIn('employee_id', $employeeIds)
                ->orWhereIn('department_id', $employees->pluck('department_id')->filter()->unique())
                ->orWhereIn('branch_id', $assignments->flatten(1)->pluck('branch_id')->unique())
                ->orWhere(fn (Builder $default) => $default->whereNull('employee_id')->whereNull('department_id')->whereNull('branch_id')))
            ->orderByRaw(self::SCHEDULE_PRIORITY)->get();
        $holidays = AttendanceHoliday::with('branches')->where('status', 'active')->where('holiday_date', '>=', $start)->where('holiday_date', '<', $until->addDay()->toDateString())
            ->orderBy('id')->get()->groupBy(fn (AttendanceHoliday $holiday) => $holiday->holiday_date->toDateString());
        $exceptions = WorkScheduleException::with('workSchedule')->whereIn('employee_id', $employeeIds)->where('active', true)
            ->where('work_date', '>=', $start)->where('work_date', '<', $until->addDay()->toDateString())->get()->groupBy('employee_id');
        $leaves = LeaveRequest::whereIn('employee_id', $employeeIds)->where('status', 'approved')
            ->whereDate('start_date', '<=', $end)->where('end_date', '>=', $start)->orderBy('id')->get()->groupBy('employee_id');

        $policies = [];
        foreach ($employees as $employee) {
            for ($day = $from; $day->lte($until); $day = $day->addDay()) {
                $date = $day->toDateString();
                $branch = $assignments->get($employee->id, collect())->first(fn (EmployeeBranchAssignment $assignment) => $assignment->effective_from->lt($day->addDay()) && (! $assignment->effective_until || $assignment->effective_until->gt($day)))?->branch;
                $location = self::location($branch);
                $base = $schedules->first(fn (WorkSchedule $schedule) => $schedule->effective_from->toDateString() <= $date
                    && (! $schedule->effective_until || $schedule->effective_until->toDateString() >= $date)
                    && match (true) {
                        $schedule->employee_id !== null => $schedule->employee_id === $employee->id,
                        $schedule->department_id !== null => $schedule->department_id === $employee->department_id,
                        $schedule->branch_id !== null => $schedule->branch_id === $branch?->id,
                        default => true,
                    });
                $declarations = $holidays->get($date, collect())->filter(fn (AttendanceHoliday $holiday) => $holiday->appliesTo($branch?->id, $location))
                    ->map->snapshot()->values()->all();
                $exception = $exceptions->get($employee->id, collect())->first(fn (WorkScheduleException $exception) => $exception->work_date->toDateString() === $date);
                $approved = $leaves->get($employee->id, collect())->filter(fn (LeaveRequest $leave) => $leave->start_date->toDateString() <= $date && $leave->end_date->toDateString() >= $date);
                $policies[$employee->id][$date] = $this->assemble($date, $branch?->id, $location, $base?->snapshot(), $declarations, $exception, $approved);
            }
        }

        return $policies;
    }

    private function assemble(string $date, ?int $branchId, ?string $location, ?array $base, array $holidays, ?WorkScheduleException $exception, Collection $leaves): array
    {
        $leaveSlots = $leaves->pluck('half_day')->unique();
        $fullLeave = $leaves->isNotEmpty() && ($leaveSlots->containsStrict(null) || $leaveSlots->count() > 1);
        $policy = [
            'date' => $date, 'timezone' => config('attendance.timezone'), 'branch_id' => $branchId, 'rules_version' => 1,
            'location' => $location, 'base_schedule' => $base, 'schedule' => $exception?->workSchedule?->snapshot() ?? $base,
            'holidays' => $holidays,
            'exception' => $exception?->only(['id', 'kind', 'reason', 'created_by', 'adjusted_end']),
            'adjusted_end' => null,
            'leave_ids' => $leaves->pluck('id')->values()->all(), 'full_leave' => $fullLeave,
            'partial_leave' => $fullLeave ? null : $leaveSlots->first(),
        ];

        return $this->classify($policy);
    }

    public function obligationWithoutLeave(array $policy): string
    {
        return $this->classify([...$policy, 'full_leave' => false])['obligation'];
    }

    private function classify(array $policy): array
    {
        $schedule = $policy['schedule'];
        $kind = $policy['exception']['kind'] ?? null;
        $date = CarbonImmutable::parse($policy['date'], $policy['timezone']);
        $nonWorkingHoliday = collect($policy['holidays'])->contains(fn (array $holiday): bool => ! $holiday['work_expected']);
        $scheduledDay = $schedule && in_array($date->dayOfWeekIso, $schedule['working_days']);
        $policy['obligation'] = match (true) {
            $policy['full_leave'] => 'approved_leave',
            $kind === 'excused' => 'excused',
            $kind === 'shift' && $schedule !== null => 'work',
            $nonWorkingHoliday, $schedule !== null && ! $scheduledDay => 'not_scheduled',
            $schedule !== null => 'work',
            default => 'no_schedule',
        };
        $adjustedEnd = $policy['exception']['adjusted_end'] ?? null;
        if ($kind === 'early_departure' && $schedule && $adjustedEnd) {
            $offset = WorkDuration::offset($adjustedEnd, $schedule['starts_at']);
            $policy['adjusted_end'] = $offset > 0 && $offset < WorkDuration::offset($schedule['ends_at'], $schedule['starts_at']) ? $adjustedEnd : null;
        }

        return $policy;
    }

    public function withoutPolicy(string $date, ?int $branchId, ?string $location): array
    {
        return [
            'date' => $date, 'timezone' => config('attendance.timezone'), 'branch_id' => $branchId, 'rules_version' => 1,
            'location' => $location, 'base_schedule' => null, 'schedule' => null, 'holidays' => [],
            'exception' => null, 'adjusted_end' => null, 'leave_ids' => [], 'full_leave' => false,
            'partial_leave' => null, 'obligation' => 'no_schedule',
        ];
    }

    /** @return list<string> */
    public function leaveHolidayDates(Employee $employee, CarbonImmutable $from, CarbonImmutable $until, bool $nonWorkingOnly = false): array
    {
        $dates = [];
        $holidays = AttendanceHoliday::with('branches')->where('status', 'active')->when($nonWorkingOnly, fn (Builder $query) => $query->where('work_expected', false))
            ->where('holiday_date', '>=', $from->toDateString())->where('holiday_date', '<', $until->addDay()->toDateString())->orderBy('holiday_date')->get();
        foreach ($holidays->groupBy(fn (AttendanceHoliday $holiday) => $holiday->holiday_date->toDateString()) as $date => $declarations) {
            $branch = $this->branchFor($employee, $date);
            if ($declarations->contains(fn (AttendanceHoliday $holiday) => $holiday->appliesTo($branch?->id, self::location($branch)))
                && (! $nonWorkingOnly || ! WorkScheduleException::where('employee_id', $employee->id)->whereDate('work_date', $date)->where('active', true)->where('kind', 'shift')->exists())) {
                $dates[] = $date;
            }
        }

        return $dates;
    }
}
