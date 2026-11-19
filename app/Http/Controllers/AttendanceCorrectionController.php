<?php

namespace App\Http\Controllers;

use App\Http\Requests\AttendanceCorrectionRequest as StoreCorrection;
use App\Http\Requests\CorrectionDecisionRequest;
use App\Models\AttendanceCorrectionRequest;
use App\Models\AttendanceRecord;
use App\Services\AttendanceCorrectionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class AttendanceCorrectionController extends Controller
{
    public function index(Request $request): View
    {
        $hr = $request->routeIs('hr.*');
        abort_if($request->user()->isSupervisor(), 403);
        $data = $request->validate(['status' => ['nullable', 'in:pending,approved,rejected,cancelled']]);
        $query = AttendanceCorrectionRequest::with(['employee.user', 'branch', 'reviewer'])->when(! $hr, fn ($q) => $q->where('employee_id', $request->user()->employee?->id ?? 0))->when($data['status'] ?? null, fn ($q, $value) => $q->where('status', $value));

        return view('corrections.index', ['corrections' => $query->latest('id')->paginate(15)->withQueryString(), 'hr' => $hr]);
    }

    public function create(Request $request): View
    {
        abort_unless(! $request->user()->isSupervisor() && $request->user()->employee, 403);
        $records = AttendanceRecord::where('employee_id', $request->user()->employee->id)->latest('id')->limit(100)->get();
        $selected = null;
        if ($request->filled('record_id')) {
            $selected = AttendanceRecord::where('employee_id', $request->user()->employee->id)->findOrFail($request->integer('record_id'));
            if (! $records->contains('id', $selected->id)) {
                $records->prepend($selected);
            }
        }

        return view('corrections.create', compact('records', 'selected'));
    }

    public function store(StoreCorrection $request, AttendanceCorrectionService $service): RedirectResponse
    {
        $correction = $service->submit($request->user(), $request->validated());

        return redirect()->route('employee.corrections.show', $correction)->with('status', 'Attendance correction submitted for HR review.');
    }

    public function show(Request $request, AttendanceCorrectionRequest $correction): View
    {
        Gate::authorize('view', $correction);
        if ($request->routeIs('employee.*')) {
            abort_unless($correction->employee->user_id === $request->user()->id, 403);
        }

        return view('corrections.show', ['correction' => $correction->load(['employee.user', 'branch', 'reviewer', 'record.events.photo', 'computation']), 'hr' => $request->routeIs('hr.*'), 'history' => AttendanceCorrectionRequest::where('employee_id', $correction->employee_id)->when($correction->attendance_record_id, fn ($q, $id) => $q->where('attendance_record_id', $id), fn ($q) => $q->whereNull('attendance_record_id')->whereDate('requested_date', $correction->requested_date))->latest('id')->get()]);
    }

    public function decide(CorrectionDecisionRequest $request, AttendanceCorrectionRequest $correction, AttendanceCorrectionService $service): RedirectResponse
    {
        $service->decide($request->user(), $correction, $request->validated());

        return back()->with('status', 'Attendance correction '.$request->validated('decision').'.');
    }

    public function cancel(Request $request, AttendanceCorrectionRequest $correction, AttendanceCorrectionService $service): RedirectResponse
    {
        $request->validate(['confirmation' => ['accepted']]);
        $service->cancel($request->user(), $correction);

        return back()->with('status', 'Correction request cancelled.');
    }
}
