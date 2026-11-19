<?php

namespace App\Http\Controllers;

use App\Models\AttendanceCorrectionRequest;
use App\Models\AttendanceRecord;
use App\Models\LeaveRequest;
use App\Services\AttendancePolicyResolver;
use App\Services\WorkDuration;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\View\View;

class EmployeeWorkCalendarController extends Controller
{
    public function __invoke(Request $request, AttendancePolicyResolver $resolver, WorkDuration $duration): View
    {
        abort_if($request->user()->isSupervisor(), 403);
        $employee = $request->user()->employee;
        $data = $request->validate([
            'month' => ['nullable', 'date_format:Y-m'],
            'day' => ['nullable', 'date_format:Y-m-d'],
            'details' => ['nullable', 'boolean'],
        ]);
        $today = CarbonImmutable::today(config('attendance.timezone'));
        $selected = isset($data['day']) ? CarbonImmutable::parse($data['day'], $today->timezone) : null;
        $month = CarbonImmutable::parse(($data['month'] ?? $selected?->format('Y-m') ?? $today->format('Y-m')).'-01', $today->timezone);
        abort_if($selected && $selected->format('Y-m') !== $month->format('Y-m'), 404);

        $dates = $employee ? $resolver->forPeriod($employee->newCollection([$employee]), $month, $month->endOfMonth()->startOfDay())[$employee->id] : [];
        $start = $month->toDateString();
        $end = $month->addMonth()->toDateString();
        $leaves = LeaveRequest::where('employee_id', $employee?->id ?? 0)->where('status', 'approved')
            ->where('end_date', '>=', $start)->where('start_date', '<', $end)
            ->orderBy('start_date')->orderBy('id')->get()->keyBy('id');

        $details = null;
        if ($selected && isset($dates[$selected->toDateString()])) {
            $date = $selected->toDateString();
            $policy = $dates[$date];
            $records = AttendanceRecord::where('employee_id', $employee->id)->whereDate('work_date', $date)
                ->with('computation')->orderBy('id')->get();
            $manualEntries = AttendanceCorrectionRequest::where('employee_id', $employee->id)->whereNull('attendance_record_id')
                ->where('status', 'approved')->whereDate('requested_date', $date)->with('computation')->orderBy('id')->get();
            $expected = $duration->calculate($policy, null, null)['expected_minutes'];
            $details = compact('selected', 'policy', 'records', 'manualEntries', 'expected') + [
                'dayLeaves' => $leaves->only($policy['leave_ids']),
            ];
        }

        if ($request->boolean('details')) {
            abort_unless($details, 404);

            return view('attendance.policies.employee-day', $details);
        }

        return view('attendance.policies.employee-calendar', compact('dates', 'month', 'today', 'leaves', 'details'));
    }
}
