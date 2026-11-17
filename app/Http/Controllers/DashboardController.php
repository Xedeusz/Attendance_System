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
        return view('hr.dashboard', [
            'stats' => ['Total employees' => Employee::count(), 'Active employee accounts' => Employee::whereHas('user', fn ($query) => $query->where('account_status', 'active'))->count(), 'Pending account activations' => Employee::whereHas('user', fn ($query) => $query->where('account_status', 'pending_activation'))->count(), 'Active branches' => Branch::where('status', 'active')->count(), 'Departments' => Department::count()],
            'recent' => Employee::with(['user', 'department', 'currentAssignment.branch'])->latest('id')->limit(5)->get(),
            'pending' => Employee::with('user')->whereHas('user', fn ($query) => $query->where('account_status', 'pending_activation'))->latest('id')->limit(5)->get(),
            'branches' => Branch::withCount('currentAssignments')->orderBy('branch_name')->limit(10)->get(),
            'attendanceStats' => $attendance->statistics(),
            'activity' => $attendance->activity(),
            'businessDate' => now(config('attendance.timezone')),
            'leaveStats' => ['Awaiting Supervisor assignment' => LeaveRequest::where('status', 'awaiting_supervisor_assignment')->count(), 'Pending Supervisor approval' => LeaveRequest::where('status', 'pending_supervisor_approval')->count(), 'Pending attendance corrections' => AttendanceCorrectionRequest::where('status', 'pending')->count(), 'Approved leave applications' => LeaveRequest::where('status', 'approved')->count(), 'Rejected leave applications' => LeaveRequest::where('status', 'rejected')->count(), 'Completed correction reviews' => AttendanceCorrectionRequest::whereIn('status', ['approved', 'rejected'])->count()],
        ]);
    }

    public function employee(Request $request, AttendanceService $attendance): View
    {
        $user = $request->user()->load(['employee.department', 'employee.currentAssignment.branch']);

        return view('employee.dashboard', ['recentLeave' => LeaveRequest::with('type')->where('employee_id', $user->employee?->id ?? 0)->latest('id')->limit(3)->get(), 'pendingLeave' => LeaveRequest::where('employee_id', $user->employee?->id ?? 0)->whereIn('status', LeaveRequest::PENDING)->count(), 'user' => $user, 'open' => $user->employee ? $attendance->open($user->employee) : null, 'records' => AttendanceRecord::where('employee_id', $user->employee?->id ?? 0)->with('branch')->latest('id')->limit(5)->get()]);
    }
}
