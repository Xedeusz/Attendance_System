<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\Department;
use App\Models\LeaveType;
use App\Models\Role;
use App\Models\User;
use App\Services\LeaveCalendar;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\Cursor;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class LeaveCalendarController extends Controller
{
    public function index(Request $request): View
    {
        $role = $this->role($request);
        $filters = $role === 'hr' ? [
            'branch_id' => ['Branch', Branch::orderBy('branch_name')->pluck('branch_name', 'id')],
            'department_id' => ['Department', Department::orderBy('department_name')->pluck('department_name', 'id')],
            'supervisor_id' => ['Supervisor', User::where('role_id', Role::SUPERVISOR)->orderBy('name')->pluck('name', 'id')],
            'leave_type_id' => ['Leave type', LeaveType::orderBy('name')->pluck('name', 'id')],
        ] : [];

        return view('leave.calendar', [
            'role' => $role,
            'prefix' => $role.'.leave',
            'statuses' => $this->statuses($role),
            'filters' => $filters,
            'today' => CarbonImmutable::today(config('attendance.timezone'))->toDateString(),
        ]);
    }

    public function events(Request $request, LeaveCalendar $calendar): JsonResponse
    {
        $role = $this->role($request);
        $allowed = match ($role) {
            'hr' => ['branch_id', 'department_id', 'supervisor_id', 'leave_type_id', 'employee_id', 'employee'],
            'supervisor' => ['employee_id'],
            default => [],
        };
        $rules = [
            'from' => ['required', 'date_format:Y-m-d'],
            'to' => ['required', 'date_format:Y-m-d', 'after_or_equal:from'],
            'status' => ['nullable', Rule::in(array_keys($this->statuses($role)))],
            'cursor' => ['nullable', 'string', 'max:180', 'regex:/\A[A-Za-z0-9_-]+\z/'],
        ];
        foreach (['branch_id', 'department_id', 'supervisor_id', 'leave_type_id', 'employee_id', 'employee'] as $filter) {
            $rules[$filter] = in_array($filter, $allowed, true)
                ? ($filter === 'employee' ? ['nullable', 'string', 'max:100'] : ['nullable', 'integer', 'min:1'])
                : ['prohibited'];
        }

        $data = $request->validate($rules);
        $start = CarbonImmutable::parse($data['from'], config('attendance.timezone'));
        $end = CarbonImmutable::parse($data['to'], config('attendance.timezone'));
        if ($start->addDays(41)->lt($end)) {
            throw ValidationException::withMessages(['to' => 'Choose a calendar range of up to 42 days.']);
        }
        $cursor = isset($data['cursor']) ? Cursor::fromEncoded($data['cursor']) : null;
        $position = $cursor?->toArray() ?? [];
        if (isset($data['cursor']) && (
            ($position['_pointsToNextItems'] ?? false) !== true
            || ! is_string($position['calendar_start'] ?? null)
            || ! preg_match('/\A\d{4}-\d{2}-\d{2}(?: 00:00:00)?\z/', $position['calendar_start'])
            || ! filter_var($position['id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]])
        )) {
            throw ValidationException::withMessages(['cursor' => 'The calendar page reference is invalid.']);
        }

        return response()->json($calendar->load($request->user(), $role, $start, $end, $data, $cursor));
    }

    public function details(Request $request, int $event, LeaveCalendar $calendar): JsonResponse
    {
        $role = $this->role($request);
        $leave = $calendar->scope($request->user(), $role)->with(['employee.department', 'employee.currentAssignment.branch', 'supervisor', 'approvalDocument'])->findOrFail($event);
        Gate::authorize($role === 'supervisor' ? 'review' : 'view', $leave);
        $prefix = $role.'.leave';
        $timezone = config('attendance.timezone');
        $remarks = $role === 'employee'
            ? $leave->reviews()->reorder()->whereIn('action', ['approved', 'rejected'])->latest('id')->value('remarks')
            : null;

        return response()->json([
            'title' => $role === 'employee' ? ($leave->type_snapshot['name'] ?? 'Leave') : $leave->employee->full_name,
            'employee_number' => $role === 'hr' ? $leave->employee->employee_number : null,
            'department' => $role === 'hr' ? $leave->employee->department?->department_name : null,
            'branch' => $role === 'hr' ? $leave->employee->currentAssignment?->branch?->branch_name : null,
            'type' => $leave->type_snapshot['name'] ?? null,
            'start' => $leave->start_date->toDateString(),
            'end' => $leave->end_date->toDateString(),
            'duration' => $leave->requested_duration,
            'half_day' => $leave->half_day,
            'status' => $leave->status,
            'supervisor' => $leave->supervisor?->name,
            'submitted' => $leave->submitted_at->timezone($timezone)->format('M j, Y H:i'),
            'decision' => ($leave->status === 'revoked' ? $leave->approvalDocument?->revoked_at : $leave->reviewed_at)?->timezone($timezone)->format('M j, Y H:i'),
            'remarks' => $remarks,
            'url' => route($prefix.'.show', $leave, false),
            'pdf_url' => $leave->status === 'approved' && $leave->approvalDocument && ! $leave->approvalDocument->revoked_at
                ? route($prefix.'.approved-form', $leave, false) : null,
        ]);
    }

    private function statuses(string $role): array
    {
        return $role === 'supervisor' ? array_diff_key(LeaveCalendar::STATUSES, ['awaiting_supervisor_assignment' => true]) : LeaveCalendar::STATUSES;
    }

    private function role(Request $request): string
    {
        $actor = $request->user();
        abort_unless($actor->canAuthenticate(), 403);

        return match (true) {
            $request->routeIs('hr.leave.calendar.*') && $actor->isHr() => 'hr',
            $request->routeIs('supervisor.leave.calendar.*') && $actor->isSupervisor() => 'supervisor',
            $request->routeIs('employee.leave.calendar.*') && $actor->isEmployee() => 'employee',
            default => abort(403),
        };
    }
}
