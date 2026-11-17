<?php

namespace App\Http\Controllers;

use App\Http\Requests\LeaveApplicationRequest;
use App\Http\Requests\LeaveDecisionRequest;
use App\Models\Employee;
use App\Models\EmployeeLeaveBalance;
use App\Models\LeaveRequest;
use App\Models\LeaveSupportingDocument;
use App\Models\LeaveType;
use App\Models\SupervisorAssignment;
use App\Services\LeaveService;
use App\Services\WorkspaceSync;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class LeaveController extends Controller
{
    public function index(Request $request): View
    {
        $actor = $request->user();
        $scope = $request->routeIs('supervisor.*') ? 'supervisor' : 'employee';
        if ($scope === 'supervisor') {
            abort_unless($actor->isSupervisor(), 403);
        }
        $query = LeaveRequest::with(['employee.user', 'employee.department', 'employee.currentAssignment.branch', 'type', 'supervisor']);
        $scope === 'supervisor' ? $query->forSupervisor($actor) : $query->where('employee_id', $actor->employee?->id ?? 0);
        $stats = (clone $query)->selectRaw('status,COUNT(*) AS total')->groupBy('status')->pluck('total', 'status')->all();
        $data = $request->validate(['q' => ['nullable', 'string', 'max:100'], 'from' => ['nullable', 'date_format:Y-m-d'], 'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'], 'status' => ['nullable', 'in:awaiting_supervisor_assignment,pending_supervisor_approval,approved,rejected,cancelled']]);
        if ($request->routeIs('supervisor.leave.pending')) {
            $query->where('status', 'pending_supervisor_approval');
        }
        if ($request->routeIs('supervisor.leave.history')) {
            $query->whereIn('status', ['approved', 'rejected', 'cancelled']);
        }
        $query->when($data['q'] ?? null, fn ($q, $term) => $q->whereHas('employee', fn ($q) => $q->where(fn ($q) => $q->where('first_name', 'like', '%'.$term.'%')->orWhere('last_name', 'like', '%'.$term.'%')->orWhere('employee_number', 'like', '%'.$term.'%'))))->when($data['from'] ?? null, fn ($q, $date) => $q->whereDate('end_date', '>=', $date))->when($data['to'] ?? null, fn ($q, $date) => $q->whereDate('start_date', '<=', $date));
        $query->when($data['status'] ?? null, fn ($q, $status) => $q->where('status', $status));

        $leaves = $query->latest('id')->paginate(15)->withQueryString();
        $profileEmployeeIds = $scope === 'supervisor' ? SupervisorAssignment::current()->whereNull('revoked_at')->where('supervisor_user_id', $actor->id)->whereIn('employee_id', $leaves->pluck('employee_id'))->pluck('employee_id')->all() : [];

        return view('leave.index', ['scope' => $scope, 'leaves' => $leaves, 'profileEmployeeIds' => $profileEmployeeIds, 'stats' => $stats, 'assignedEmployees' => $scope === 'supervisor' ? Employee::with(['user.employee', 'department', 'currentAssignment.branch'])->whereIn('id', SupervisorAssignment::current()->whereNull('revoked_at')->where('supervisor_user_id', $actor->id)->select('employee_id'))->orderBy('last_name')->orderBy('id')->paginate(10, ['*'], 'employees_page')->withQueryString() : null, 'balances' => $scope === 'employee' ? EmployeeLeaveBalance::with('type')->where('employee_id', $actor->employee?->id ?? 0)->get() : collect()]);
    }

    public function create(Request $request): View
    {
        abort_unless($request->user()->employee, 403, 'An employee profile is required.');

        return view('leave.create', ['types' => LeaveType::where('is_active', true)->orderBy('name')->get()]);
    }

    public function store(LeaveApplicationRequest $request, LeaveService $service): RedirectResponse
    {
        $leave = $service->submit($request->user(), $request->validated(), $request->file('supporting_document'));

        return redirect()->route('employee.leave.show', $leave)->with('status', $leave->status === 'awaiting_supervisor_assignment' ? 'Your leave application is awaiting Supervisor assignment.' : 'Leave submitted for Supervisor approval.');
    }

    public function show(Request $request, LeaveRequest $leave): View
    {
        Gate::authorize('view', $leave);
        if ($request->routeIs('supervisor.*')) {
            abort_unless($request->user()->isSupervisor() && Gate::allows('review', $leave), 403);
        }
        if ($request->routeIs('employee.*')) {
            abort_unless($leave->employee->user_id === $request->user()->id, 403);
        }
        $scope = $request->routeIs('supervisor.*') ? 'supervisor' : ($request->routeIs('hr.*') ? 'hr' : 'employee');

        return view('leave.show', ['leave' => $leave->load(['employee.user', 'employee.department', 'employee.currentAssignment.branch', 'supervisor', 'type', 'reviews.reviewer', 'documents']), 'scope' => $scope, 'balance' => EmployeeLeaveBalance::where('employee_id', $leave->employee_id)->where('leave_type_id', $leave->leave_type_id)->where('policy_period', $leave->start_date->format('Y'))->first()]);
    }

    public function decide(LeaveDecisionRequest $request, LeaveRequest $leave, LeaveService $service): RedirectResponse
    {
        $service->decide($request->user(), $leave, $request->validated());

        return back()->with('status', 'Leave application '.$request->validated('decision').'.');
    }

    public function cancel(Request $request, LeaveRequest $leave, LeaveService $service): RedirectResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'min:10', 'max:1000'], 'confirmation' => ['accepted']]);
        $service->cancel($request->user(), $leave, $data['reason']);

        return back()->with('status', 'Leave application cancelled.');
    }

    public function document(Request $request, LeaveSupportingDocument $document): StreamedResponse
    {
        $leave = $document->leave;
        if ($request->routeIs('supervisor.*')) {
            abort_unless($request->user()->isSupervisor(), 403);
        }
        if ($request->routeIs('employee.*')) {
            abort_unless($leave->employee->user_id === $request->user()->id, 403);
        }
        Gate::authorize('view', $leave);
        abort_if($document->purged_at || ! Storage::disk('leave')->exists($document->storage_path), 404);
        $bytes = Crypt::decryptString(Storage::disk('leave')->get($document->storage_path));

        return response()->streamDownload(function () use ($bytes) {
            echo $bytes;
        }, $document->original_name, ['Content-Type' => $document->mime_type, 'Cache-Control' => 'no-store, private', 'X-Content-Type-Options' => 'nosniff', 'Content-Security-Policy' => 'sandbox']);
    }

    public function notices(Request $request): View
    {
        abort_unless($request->user()->isSupervisor(), 403);

        return view('leave.notices', ['notices' => $request->user()->notifications()->where('data->category', 'leave')->latest()->paginate(15)]);
    }

    public function readNotice(Request $request, string $notice): RedirectResponse
    {
        $query = $request->user()->notifications()->whereKey($notice);
        if ($request->user()->isSupervisor()) {
            $query->where('data->category', 'leave');
        }
        $query->firstOrFail()->markAsRead();
        app(WorkspaceSync::class)->touch($request->user(), ['notifications']);

        return back();
    }
}
