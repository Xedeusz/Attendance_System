<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\SupervisorAssignment;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\Cursor;

class LeaveCalendar
{
    public const STATUSES = [
        'approved' => 'Approved',
        'pending_supervisor_approval' => 'Pending Supervisor approval',
        'awaiting_supervisor_assignment' => 'Awaiting assignment',
        'rejected' => 'Rejected',
        'cancelled' => 'Cancelled',
        'revoked' => 'Revoked',
    ];

    private const ACTIVE = ['approved', ...LeaveRequest::PENDING];

    public function scope(User $actor, string $role): Builder
    {
        $query = LeaveRequest::query();

        return match ($role) {
            'hr' => $query,
            'supervisor' => $query->forSupervisor($actor)->whereIn('employee_id', $this->assignedEmployees($actor->id))->where('status', '!=', 'awaiting_supervisor_assignment'),
            default => $query->where('employee_id', $actor->employee?->id ?? 0),
        };
    }

    public function teamEmployees(User $actor): Collection
    {
        return $this->employees($actor, 'supervisor', [])->orderBy('last_name')->orderBy('first_name')
            ->get(['id', 'first_name', 'middle_name', 'last_name', 'suffix']);
    }

    public function load(User $actor, string $role, CarbonImmutable $start, CarbonImmutable $end, array $filters, ?Cursor $cursor): array
    {
        $base = $this->scope($actor, $role);
        if ($role !== 'employee') {
            $employees = $this->employees($actor, $role, $filters);
            if ($role === 'hr' || isset($filters['employee_id'])) {
                $base->whereIn('employee_id', (clone $employees)->select('employees.id'));
            }
        }
        if (isset($filters['supervisor_id'])) {
            $base->where('assigned_supervisor_user_id', $filters['supervisor_id']);
        }

        $visible = $this->during(clone $base, $start, $end)
            ->whereIn('status', isset($filters['status']) ? [$filters['status']] : self::ACTIVE)
            ->when(isset($filters['leave_type_id']), fn (Builder $query) => $query->where('leave_type_id', $filters['leave_type_id']));
        $events = $visible->select(['id', 'employee_id', 'type_snapshot', 'start_date', 'start_date as calendar_start', 'end_date', 'status', 'half_day'])
            ->with('employee:id,first_name,middle_name,last_name,suffix')
            ->orderBy('calendar_start')->orderBy('id')->cursorPaginate(150, cursor: $cursor);
        $today = CarbonImmutable::today(config('attendance.timezone'));

        return [
            'today' => $today->toDateString(),
            'events' => $events->getCollection()->map(fn (LeaveRequest $leave): array => [
                'id' => $leave->id,
                'title' => $role === 'employee' ? ($leave->type_snapshot['name'] ?? 'Leave') : $leave->employee->full_name,
                'start' => $leave->start_date->toDateString(),
                'end' => $leave->end_date->toDateString(),
                'status' => $leave->status,
                'half_day' => $leave->half_day,
            ]),
            'next_cursor' => $events->nextCursor()?->encode(),
            'staffing' => $role !== 'employee' && ! $cursor ? $this->staffing($base, $employees, $role, $start, $end, $today, $filters['supervisor_id'] ?? null) : null,
            'team' => $role === 'supervisor' && ! $cursor
                ? $this->teamEmployees($actor)->map(fn (Employee $employee): array => ['id' => $employee->id, 'name' => $employee->full_name])
                : null,
        ];
    }

    private function employees(User $actor, string $role, array $filters): Builder
    {
        $query = Employee::query();
        if ($role === 'supervisor') {
            $query->whereIn('employees.id', $this->assignedEmployees($actor->id))->where('user_id', '!=', $actor->id);
        }
        if (isset($filters['employee_id'])) {
            $query->whereKey($filters['employee_id']);
        }
        if (isset($filters['department_id'])) {
            $query->where('department_id', $filters['department_id']);
        }
        if (isset($filters['branch_id'])) {
            $query->whereHas('currentAssignment', fn (Builder $assignment) => $assignment->where('branch_id', $filters['branch_id']));
        }
        if (isset($filters['employee'])) {
            foreach (preg_split('/\s+/u', $filters['employee'], flags: PREG_SPLIT_NO_EMPTY) ?: [] as $term) {
                $search = '%'.$term.'%';
                $query->where(fn (Builder $names) => $names->whereLike('first_name', $search)->orWhereLike('last_name', $search)
                    ->orWhereLike('middle_name', $search)->orWhereLike('employee_number', $search));
            }
        }

        return $query;
    }

    private function assignedEmployees(int $supervisor): Builder
    {
        return SupervisorAssignment::current()->whereNull('revoked_at')->where('supervisor_user_id', $supervisor)->select('employee_id');
    }

    private function during(Builder $query, CarbonImmutable $start, CarbonImmutable $end): Builder
    {
        return $query->where('end_date', '>=', $start->toDateString())->where('start_date', '<', $end->addDay()->toDateString());
    }

    private function staffing(Builder $base, Builder $employees, string $role, CarbonImmutable $start, CarbonImmutable $end, CarbonImmutable $today, ?int $supervisor): array
    {
        $employees->where('employment_status', 'active');
        if ($supervisor !== null) {
            $employees->whereIn('employees.id', $this->assignedEmployees($supervisor));
        }
        $headcount = (clone $employees)->count();
        $population = $base->whereIn('employee_id', $employees->select('employees.id'));
        $schedule = $this->during(clone $population, $start, $end)->whereIn('status', self::ACTIVE)
            ->select(['id', 'employee_id', 'start_date', 'end_date', 'status', 'half_day']);
        if ($role === 'hr') {
            $schedule->with(['employee:id,department_id', 'employee.currentAssignment:id,employee_id,branch_id']);
        }

        $days = [];
        $teams = [];
        $scheduled = [];
        $awaiting = $pendingRequests = 0;
        foreach ($schedule->lazyById(300) as $leave) {
            $approved = $leave->status === 'approved';
            if ($approved) {
                $scheduled[$leave->employee_id] = true;
            } else {
                $pendingRequests++;
                $awaiting += (int) ($leave->status === 'awaiting_supervisor_assignment');
            }
            $group = $role === 'hr' ? $leave->employee->department_id.':'.$leave->employee->currentAssignment?->branch_id : 'team';
            $bucket = $approved ? 'approved' : 'pending';
            $session = match ($leave->half_day) {
                'am' => 1,
                'pm' => 2,
                default => 3,
            };
            $firstDay = max($start->toDateString(), $leave->start_date->toDateString());
            $lastDay = min($end->toDateString(), $leave->end_date->toDateString());
            for ($day = CarbonImmutable::parse($firstDay); $day->toDateString() <= $lastDay; $day = $day->addDay()) {
                $date = $day->toDateString();
                $employeeId = $leave->employee_id;
                $days[$date][$bucket][$employeeId] = ($days[$date][$bucket][$employeeId] ?? 0) | $session;
                foreach ([1, 2] as $slot) {
                    if ($session & $slot) {
                        $teams[$date][$group][$slot][$bucket][$employeeId] = true;
                    }
                }
            }
        }

        $todayKey = $today->toDateString();
        $approvedToday = $today->betweenIncluded($start, $end)
            ? count($days[$todayKey]['approved'] ?? [])
            : $this->during(clone $population, $today, $today)->where('status', 'approved')->distinct()->count('employee_id');
        $daily = [];
        foreach ($days as $date => $counts) {
            $approved = $counts['approved'] ?? [];
            $flags = [];
            foreach ($teams[$date] ?? [] as $slots) {
                foreach ($slots as $buckets) {
                    $approvedSlot = $buckets['approved'] ?? [];
                    $pendingSlot = $buckets['pending'] ?? [];
                    if (count($approvedSlot) > 1) {
                        $flags['approved_approved'] = true;
                    }
                    if ($approvedSlot && $pendingSlot && count($approvedSlot + $pendingSlot) > 1) {
                        $flags['approved_pending'] = true;
                    }
                    if (count($pendingSlot) > 1) {
                        $flags['pending_pending'] = true;
                    }
                }
            }
            $daily[$date] = [
                'approved' => count($approved),
                'pending' => count($counts['pending'] ?? []),
                'approved_half_day' => count(array_filter($approved, fn (int $mask): bool => $mask !== 3)),
                'overlaps' => array_keys($flags),
            ];
        }

        return [
            'employees' => $headcount,
            'approved_today' => $approvedToday,
            'scheduled' => count($scheduled),
            'pending_requests' => $pendingRequests,
            'awaiting_requests' => $awaiting,
            'days' => (object) $daily,
        ];
    }
}
