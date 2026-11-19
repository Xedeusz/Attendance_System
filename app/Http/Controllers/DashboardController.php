<?php

namespace App\Http\Controllers;

use App\Models\AttendanceCorrectionRequest;
use App\Models\AttendanceRecord;
use App\Models\Branch;
use App\Models\Department;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Services\AttendanceComputationService;
use App\Services\AttendancePolicyResolver;
use App\Services\AttendanceQueries;
use App\Services\AttendanceService;
use App\Services\WorkDuration;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function hr(AttendanceQueries $attendance): View
    {
        $employeeAccounts = Employee::query()->join('users', 'users.id', '=', 'employees.user_id')
            ->toBase()->selectRaw('COUNT(*) AS total')
            ->selectRaw('COUNT(CASE WHEN users.account_status = ? THEN 1 END) AS active', ['active'])
            ->selectRaw('COUNT(CASE WHEN users.account_status = ? THEN 1 END) AS pending', ['pending_activation'])->first();
        $leaveCounts = LeaveRequest::whereIn('status', ['awaiting_supervisor_assignment', 'pending_supervisor_approval', 'approved', 'rejected'])
            ->toBase()->selectRaw('status, COUNT(*) AS total')->groupBy('status')->pluck('total', 'status');
        $correctionCounts = AttendanceCorrectionRequest::whereIn('status', ['pending', 'approved', 'rejected'])
            ->toBase()->selectRaw('status, COUNT(*) AS total')->groupBy('status')->pluck('total', 'status');

        return view('hr.dashboard', [
            'stats' => [
                'Total employees' => (int) $employeeAccounts->total,
                'Active employee accounts' => (int) $employeeAccounts->active,
                'Pending account activations' => (int) $employeeAccounts->pending,
                'Active branches' => Branch::where('status', 'active')->count(),
                'Departments' => Department::count(),
            ],
            'recent' => Employee::with(['user', 'department', 'currentAssignment.branch'])->latest('id')->limit(5)->get(),
            'pending' => Employee::whereHas('user', fn ($query) => $query->where('account_status', 'pending_activation'))->latest('id')->limit(5)->get(),
            'branches' => Branch::withCount('currentAssignments')->orderBy('branch_name')->limit(10)->get(),
            'attendanceStats' => $attendance->statistics(),
            'dailyPolicyStats' => $attendance->dailyPolicyStatistics(),
            'activity' => $attendance->activity(),
            'businessDate' => now(config('attendance.timezone')),
            'leaveStats' => [
                'Awaiting Supervisor assignment' => (int) ($leaveCounts['awaiting_supervisor_assignment'] ?? 0),
                'Pending Supervisor approval' => (int) ($leaveCounts['pending_supervisor_approval'] ?? 0),
                'Pending attendance corrections' => (int) ($correctionCounts['pending'] ?? 0),
                'Approved leave applications' => (int) ($leaveCounts['approved'] ?? 0),
                'Rejected leave applications' => (int) ($leaveCounts['rejected'] ?? 0),
                'Completed correction reviews' => (int) ($correctionCounts['approved'] ?? 0) + (int) ($correctionCounts['rejected'] ?? 0),
            ],
        ]);
    }

    public function employee(Request $request, AttendanceService $attendance, AttendancePolicyResolver $policies, WorkDuration $duration, AttendanceComputationService $computations): View
    {
        $user = $request->user()->loadMissing(['employee.department', 'employee.currentAssignment.branch']);
        $employee = $user->employee;
        $employeeId = $employee?->id ?? 0;
        $workday = now(config('attendance.timezone'));
        $records = AttendanceRecord::where('employee_id', $employeeId);
        $open = $employee ? $attendance->open($employee) : null;
        $attendanceRecord = $open;

        if ($employee && ! $attendanceRecord) {
            $attendanceRecord = (clone $records)
                ->where('work_date', '>=', $workday->toDateString())
                ->where('work_date', '<', $workday->copy()->addDay()->toDateString())
                ->latest('id')->first();
        }

        $today = $workday->toDateString();
        $tomorrow = $workday->copy()->addDay()->toDateString();
        $sessions = (clone $records)->with(['computation', 'branch'])->where('work_date', '>=', $today)->where('work_date', '<', $tomorrow)->get();
        $manual = AttendanceCorrectionRequest::with(['computation', 'branch'])->where('employee_id', $employeeId)
            ->where('status', 'approved')->whereNull('attendance_record_id')->where('requested_date', '>=', $today)->where('requested_date', '<', $tomorrow)->get();
        $workPolicy = $employee ? $policies->resolve($employee, $today, $policies->branchFor($employee, $today)) : null;

        return view('employee.dashboard', [
            'user' => $user,
            'employee' => $employee,
            'workday' => $workday,
            'open' => $open,
            'attendanceRecord' => $attendanceRecord?->load('computation'),
            'workPolicy' => $workPolicy,
            'dayComputation' => $computations->day(collect([...$sessions, ...$manual])),
            'manualAttendance' => $manual->isNotEmpty(),
            'expectedToday' => $workPolicy ? $duration->calculate($workPolicy, null, null)['expected_minutes'] : null,
            'records' => $records->with('branch')->latest('id')->limit(5)->get(),
            'recentLeave' => LeaveRequest::where('employee_id', $employeeId)->latest('id')->limit(3)->get(),
        ]);
    }
}
