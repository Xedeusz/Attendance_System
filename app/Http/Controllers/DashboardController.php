<?php

namespace App\Http\Controllers;

use App\Models\AttendanceAssistanceRequest;
use App\Models\AttendanceCorrectionRequest;
use App\Models\AttendanceEvent;
use App\Models\AttendanceRecord;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Services\AttendanceComputationService;
use App\Services\AttendancePolicyResolver;
use App\Services\AttendanceQueries;
use App\Services\AttendanceService;
use App\Services\WorkDuration;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function hr(AttendanceQueries $attendance): View
    {
        $businessDate = CarbonImmutable::now(config('attendance.timezone'))->startOfDay();
        $daily = $attendance->dailyPolicyStatistics($businessDate);
        $activeEmployees = Employee::whereRelation('user', 'account_status', 'active')->count();
        $actions = array_filter([
            [
                'label' => 'Attendance submissions awaiting HR review',
                'count' => AttendanceEvent::where('review_status', 'pending')->count(),
                'icon' => 'file',
                'url' => route('hr.attendance.index', ['verification_status' => 'pending_review']),
            ],
            [
                'label' => 'Attendance corrections awaiting review',
                'count' => AttendanceCorrectionRequest::where('status', 'pending')->count(),
                'icon' => 'clock',
                'url' => route('hr.corrections.index', ['status' => 'pending']),
            ],
            [
                'label' => 'Attendance assistance requests',
                'count' => AttendanceAssistanceRequest::where('status', 'pending')->count(),
                'icon' => 'info',
                'url' => route('hr.attendance.index'),
            ],
            [
                'label' => 'Employees awaiting Supervisor assignment',
                'count' => LeaveRequest::where('status', 'awaiting_supervisor_assignment')->distinct()->count('employee_id'),
                'icon' => 'users',
                'url' => route('hr.leave.index', ['status' => 'awaiting_supervisor_assignment']),
            ],
        ], fn (array $action): bool => $action['count'] > 0);

        return view('hr.dashboard', [
            'stats' => [
                'Active Employees' => $activeEmployees,
                'Present Today' => $daily['Employees present'],
                'On Approved Leave' => $daily['Employees on approved leave'],
                'Pending HR Actions' => array_sum(array_column($actions, 'count')),
            ],
            'todayAttendance' => [
                'Employees Late' => $daily['Employees late'],
                'Employees with Undertime' => $daily['Employees with undertime'],
                'Incomplete Attendance' => $daily['Incomplete attendance'],
            ],
            'actions' => $actions,
            'businessDate' => $businessDate,
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
