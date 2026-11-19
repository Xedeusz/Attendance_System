<?php

namespace App\Http\Controllers\HR;

use App\Http\Controllers\Controller;
use App\Models\AttendanceAssistanceRequest;
use App\Models\Branch;
use App\Models\Department;
use App\Models\WorkSchedule;
use App\Services\AttendanceQueries;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AttendanceController extends Controller
{
    public function index(Request $request, AttendanceQueries $queries): View
    {
        return view('attendance.index', ['records' => $queries->records($request)->paginate(20)->withQueryString(), 'hr' => true, 'stats' => [...$queries->dailyPolicyStatistics(), ...$queries->statistics()], 'departments' => Department::orderBy('department_name')->pluck('department_name', 'id'), 'schedules' => WorkSchedule::orderBy('name')->get()->mapWithKeys(fn (WorkSchedule $schedule) => [$schedule->id => $schedule->name.' (#'.$schedule->id.')']), 'branches' => Branch::orderBy('branch_name')->get(), 'assistance' => AttendanceAssistanceRequest::with('employee.user')->where('status', 'pending')->latest('id')->paginate(10, ['*'], 'assistance_page')]);
    }
}
