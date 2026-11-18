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
use App\Models\SupervisorSignature;
use App\Services\AuditService;
use App\Services\LeaveApplicationDocumentService;
use App\Services\LeaveService;
use App\Services\WorkspaceSync;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
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
        $data = $request->validate(['q' => ['nullable', 'string', 'max:100'], 'from' => ['nullable', 'date_format:Y-m-d'], 'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'], 'status' => ['nullable', 'in:awaiting_supervisor_assignment,pending_supervisor_approval,approved,rejected,cancelled,revoked']]);
        if ($request->routeIs('supervisor.leave.pending')) {
            $query->where('status', 'pending_supervisor_approval');
        }
        if ($request->routeIs('supervisor.leave.history')) {
            $query->whereIn('status', ['approved', 'rejected', 'cancelled', 'revoked']);
        }
        $query->when($data['q'] ?? null, fn ($q, $term) => $q->whereHas('employee', fn ($q) => $q->where(fn ($q) => $q->where('first_name', 'like', '%'.$term.'%')->orWhere('last_name', 'like', '%'.$term.'%')->orWhere('employee_number', 'like', '%'.$term.'%'))))->when($data['from'] ?? null, fn ($q, $date) => $q->whereDate('end_date', '>=', $date))->when($data['to'] ?? null, fn ($q, $date) => $q->whereDate('start_date', '<=', $date));
        $query->when($data['status'] ?? null, fn ($q, $status) => $q->where('status', $status));

        $leaves = $query->latest('id')->paginate(15)->withQueryString();
        $profileEmployeeIds = $scope === 'supervisor' ? SupervisorAssignment::current()->whereNull('revoked_at')->where('supervisor_user_id', $actor->id)->whereIn('employee_id', $leaves->pluck('employee_id'))->pluck('employee_id')->all() : [];

        return view('leave.index', ['scope' => $scope, 'leaves' => $leaves, 'profileEmployeeIds' => $profileEmployeeIds, 'stats' => $stats, 'assignedEmployees' => $scope === 'supervisor' ? Employee::with(['user.employee', 'department', 'currentAssignment.branch'])->whereIn('id', SupervisorAssignment::current()->whereNull('revoked_at')->where('supervisor_user_id', $actor->id)->select('employee_id'))->orderBy('last_name')->orderBy('id')->paginate(10, ['*'], 'employees_page')->withQueryString() : null, 'balances' => $scope === 'employee' ? EmployeeLeaveBalance::with('type')->where('employee_id', $actor->employee?->id ?? 0)->get() : collect()]);
    }

    public function create(Request $request, LeaveApplicationDocumentService $forms): View
    {
        $employee = $request->user()->employee;
        abort_unless($employee, 403, 'An employee profile is required.');
        $types = LeaveType::where('is_active', true)->orderBy('name')->get();

        return view('leave.create', [
            'employee' => $employee->load('department'),
            'types' => $types,
            'dateFiled' => now(config('attendance.timezone'))->format('M j, Y'),
            'leaveChoices' => $types->groupBy($forms->categoryFor(...))->map(fn ($choices) => $choices->map(fn (LeaveType $type): array => [
                'id' => (string) $type->id,
                'name' => $type->name,
                'specify' => $forms->isGenericOther($type),
                'halfDay' => $type->allows_half_day,
                'documentRequired' => $type->requires_document,
            ])->values()),
        ]);
    }

    public function store(LeaveApplicationRequest $request, LeaveService $service): RedirectResponse
    {
        $leave = $service->submit($request->user(), $request->validated(), $request->file('supporting_document'));

        return redirect()->route('employee.leave.show', $leave)->with('status', $leave->status === 'awaiting_supervisor_assignment' ? 'Your leave application is awaiting Supervisor assignment.' : 'Leave application submitted.');
    }

    public function show(Request $request, LeaveRequest $leave): View
    {
        $scope = $this->authorizeFormView($request, $leave);
        $leave->load(['employee.user', 'employee.department', 'employee.currentAssignment.branch', 'supervisor', 'type', 'reviews.reviewer', 'documents', 'approvalDocument']);

        return view('leave.show', [
            'leave' => $leave,
            'scope' => $scope,
            'signatureVersion' => $scope === 'supervisor' && in_array($leave->status, ['pending_supervisor_approval', 'approved'], true)
                ? SupervisorSignature::activeFor($request->user())->value('version')
                : null,
            'balance' => EmployeeLeaveBalance::where('employee_id', $leave->employee_id)->where('leave_type_id', $leave->leave_type_id)->where('policy_period', $leave->start_date->format('Y'))->first(),
        ]);
    }

    public function preview(Request $request, LeaveRequest $leave, LeaveApplicationDocumentService $forms, AuditService $audit): Response|RedirectResponse
    {
        $scope = $this->authorizeFormView($request, $leave);
        if ($leave->approvalDocument) {
            return redirect()->route($scope.'.leave.approved-form', $leave);
        }
        if ($scope === 'supervisor' && $leave->status === 'pending_supervisor_approval') {
            $audit->record('leave.review_opened', $leave, ['status' => $leave->status]);
        }
        $status = match ($leave->status) {
            'rejected' => 'Leave Application — Rejected',
            'cancelled' => 'Leave Application — Cancelled',
            'approved' => 'Leave Application — Historical Record',
            default => 'Leave Application — Pending Approval',
        };

        return response()->view('leave.official-form', [
            'fields' => $forms->fields($leave),
            'templateReference' => LeaveApplicationDocumentService::TEMPLATE,
            'watermarkSource' => asset(LeaveApplicationDocumentService::WATERMARK_ASSET),
            'status' => $status,
        ])->withHeaders([
            'Cache-Control' => 'private, no-store',
            'Content-Security-Policy' => "default-src 'none'; img-src 'self'; style-src 'unsafe-inline'; frame-ancestors 'self'",
        ]);
    }

    public function approvedForm(Request $request, LeaveRequest $leave, LeaveApplicationDocumentService $forms, AuditService $audit): Response|RedirectResponse
    {
        $this->authorizeFormView($request, $leave);
        abort_unless(in_array($leave->status, ['approved', 'revoked'], true), 404);
        $document = $leave->approvalDocument()->with('signature')->firstOrFail();
        if ($document->revoked_at) {
            return redirect()->route('leave.verify.show', $document);
        }
        $pdf = $forms->bytes($document);
        $download = $request->boolean('download');
        $audit->record('leave.approved_pdf_accessed', $leave, [
            'document_reference' => $document->document_reference,
            'signature_version' => $document->signature->version,
            'mode' => $download ? 'download' : 'view',
        ]);
        $response = response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
            'Cross-Origin-Resource-Policy' => 'same-origin',
        ]);
        $response->headers->set('Content-Disposition', $response->headers->makeDisposition(
            $download ? ResponseHeaderBag::DISPOSITION_ATTACHMENT : ResponseHeaderBag::DISPOSITION_INLINE,
            $document->document_reference.'.pdf',
        ));

        return $response;
    }

    private function authorizeFormView(Request $request, LeaveRequest $leave): string
    {
        Gate::authorize('view', $leave);
        if ($request->routeIs('supervisor.*')) {
            Gate::authorize('review', $leave);

            return 'supervisor';
        }
        if ($request->routeIs('employee.*')) {
            abort_unless($leave->employee->user_id === $request->user()->id, 403);

            return 'employee';
        }

        abort_unless($request->user()->isHr(), 403);

        return 'hr';
    }

    public function decide(LeaveDecisionRequest $request, LeaveRequest $leave, LeaveService $service): RedirectResponse
    {
        $service->decide($request->user(), $leave, $request->validated());

        return redirect()->route('supervisor.leave.show', $leave)->with('status', $request->validated('decision') === 'approved'
            ? 'Leave application approved and signed. Official leave document generated.'
            : 'Leave application rejected.');
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

        return view('leave.notices', ['notices' => $request->user()->workspaceNotifications()->latest()->paginate(15)]);
    }

    public function readNotice(Request $request, string $notice): RedirectResponse
    {
        $request->user()->workspaceNotifications()->whereKey($notice)->firstOrFail()->markAsRead();
        app(WorkspaceSync::class)->touch($request->user(), ['notifications']);

        return back();
    }
}
