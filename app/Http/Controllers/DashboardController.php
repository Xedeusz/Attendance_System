<?php

namespace App\Http\Controllers;

use App\Models\AttendanceCorrectionRequest;
use App\Models\AttendanceRecord;
use App\Models\Branch;
use App\Models\Department;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Services\AttendanceQueries;
use App\Services\AttendanceService;
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

    public function employee(Request $request, AttendanceService $attendance): View
    {
        $user = $request->user()->loadMissing(['employee.department', 'employee.currentAssignment.branch']);

        return view('employee.dashboard', ['recentLeave' => LeaveRequest::where('employee_id', $user->employee?->id ?? 0)->latest('id')->limit(3)->get(), 'pendingLeave' => LeaveRequest::where('employee_id', $user->employee?->id ?? 0)->whereIn('status', LeaveRequest::PENDING)->count(), 'user' => $user, 'open' => $user->employee ? $attendance->open($user->employee) : null, 'records' => AttendanceRecord::where('employee_id', $user->employee?->id ?? 0)->with('branch')->latest('id')->limit(5)->get()]);
    }
}
