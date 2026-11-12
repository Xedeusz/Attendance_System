<?php

namespace App\Http\Controllers;

use App\Models\AttendanceRecord;
use App\Models\Branch;
use App\Models\Department;
use App\Models\Employee;
use App\Services\AttendanceQueries;
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
        ]);
    }

    public function employee(Request $request): View
    {
        $user = $request->user()->load(['employee.department', 'employee.currentAssignment.branch']);

        return view('employee.dashboard', ['user' => $user, 'records' => AttendanceRecord::where('employee_id', $user->employee?->id ?? 0)->with('branch')->latest('id')->limit(5)->get()]);
    }
}
