<?php

namespace App\Http\Controllers;

use App\Http\Requests\AttendanceSubmissionRequest;
use App\Models\AttendanceAssistanceRequest;
use App\Models\AttendanceCaptureSession;
use App\Models\AttendanceCorrectionRequest;
use App\Models\AttendanceRecord;
use App\Services\AttendanceQueries;
use App\Services\AttendanceService;
use App\Services\AuditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class EmployeeAttendanceController extends Controller
{
    public function index(Request $request, AttendanceService $attendance): View
    {
        $employee = $request->user()->employee;
        $assignment = null;
        $eligibilityError = null;
        try {
            $employee = $attendance->employee($request->user());
            $assignment = $attendance->assignment($employee);
        } catch (ValidationException $exception) {
            $eligibilityError = collect($exception->errors())->flatten()->first();
        }

        return view('attendance.capture', ['employee' => $employee, 'assignment' => $assignment, 'open' => $employee ? $attendance->open($employee) : null, 'eligibilityError' => $eligibilityError, 'serverTime' => now()->toIso8601String()]);
    }

    public function start(Request $request, AttendanceService $attendance): JsonResponse
    {
        $data = $request->validate(['action' => ['required', Rule::in(['time_in', 'time_out'])], 'employee_id' => ['prohibited'], 'branch_id' => ['prohibited']]);

        return response()->json($attendance->start($request->user(), $data['action']));
    }

    public function location(Request $request, AttendanceCaptureSession $capture, AttendanceService $attendance): JsonResponse
    {
        $data = $request->validate([...AttendanceSubmissionRequest::locationRules(), 'token' => ['required', 'string', 'size:64']]);

        return response()->json($attendance->preview($request->user(), $capture, $data));
    }

    public function submit(AttendanceSubmissionRequest $request, AttendanceCaptureSession $capture, AttendanceService $attendance, AuditService $audit): JsonResponse
    {
        $received = now();
        try {
            return response()->json($attendance->submit($request->user(), $capture, $request->validated(), $request->file('photo'), $received));
        } catch (ValidationException $exception) {
            if ($request->user()->employee) {
                $audit->record('attendance.validation_failed', $request->user()->employee, ['fields' => array_keys($exception->errors())]);
            }
            throw $exception;
        }
    }

    public function status(Request $request, AttendanceCaptureSession $capture, AttendanceService $attendance): JsonResponse
    {
        return response()->json($attendance->status($request->user(), $capture));
    }

    public function history(Request $request, AttendanceQueries $queries): View
    {
        $employeeId = $request->user()->employee?->id ?? 0;

        return view('attendance.index', ['manualEntries' => AttendanceCorrectionRequest::where('employee_id', $employeeId)->whereNull('attendance_record_id')->where('status', 'approved')->latest('id')->limit(30)->get(), 'records' => $queries->records($request, $employeeId)->paginate(15)->withQueryString(), 'hr' => false, 'stats' => [], 'branches' => collect(), 'assistance' => AttendanceAssistanceRequest::where('employee_id', $employeeId)->latest('id')->limit(10)->get()]);
    }

    public function show(AttendanceRecord $record): View
    {
        Gate::authorize('view', $record);

        return view('attendance.show', ['record' => $record->load(['employee.user.employee', 'branch', 'events.photo', 'events.review.reviewer']), 'hr' => auth()->user()->isHr()]);
    }

    public function assistance(Request $request, AttendanceService $attendance): RedirectResponse
    {
        $employee = $request->user()->employee;
        abort_unless($employee, 403);
        $data = $request->validate(['action' => ['required', Rule::in(['time_in', 'time_out'])], 'reason' => ['required', 'string', 'min:10', 'max:1000']]);
        $attendance->requestAssistance($request->user(), $data);

        return back()->with('status', 'Assistance request sent to HR. No attendance punch or verified record was created.');
    }
}
