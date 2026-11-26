<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreSpecialLeaveEligibilityRequest;
use App\Models\LeaveType;
use App\Models\SpecialLeaveEligibilityDocument;
use App\Models\SpecialLeaveEligibilityRequest;
use App\Services\LeaveTypeVisibilityService;
use App\Services\SpecialLeaveEligibilityService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SpecialLeaveEligibilityController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', SpecialLeaveEligibilityRequest::class);
        $employee = $request->user()->employee;
        abort_unless($employee, 403);

        return view('leave.eligibility.index', ['requests' => SpecialLeaveEligibilityRequest::with(['type', 'entitlement'])->whereBelongsTo($employee)->latest('id')->paginate(10)]);
    }

    public function create(Request $request, LeaveTypeVisibilityService $visibility): View
    {
        Gate::authorize('create', SpecialLeaveEligibilityRequest::class);

        $employee = $request->user()->employee;
        abort_unless($employee, 403);
        $types = LeaveType::query()->whereIn('code', ['MATERNITY', 'PATERNITY'])->where('is_active', true)->orderBy('name')->get();

        return view('leave.eligibility.create', [
            'types' => $visibility->applicableTypes($employee, $types)->pluck('name', 'id'),
            'sexRequiresCompletion' => $employee->sex === null,
        ]);
    }

    public function store(StoreSpecialLeaveEligibilityRequest $request, SpecialLeaveEligibilityService $service): RedirectResponse
    {
        $eligibilityRequest = $service->submit($request->user(), $request->validated(), $request->file('supporting_documents', []));

        return redirect()->route('employee.leave.eligibility.show', $eligibilityRequest)->with('status', 'Eligibility verification request submitted for HR review.');
    }

    public function show(SpecialLeaveEligibilityRequest $eligibilityRequest): View
    {
        Gate::authorize('view', $eligibilityRequest);

        return view('leave.eligibility.show', ['eligibilityRequest' => $eligibilityRequest->load(['type', 'documents', 'activities.actor', 'entitlement'])]);
    }

    public function update(StoreSpecialLeaveEligibilityRequest $request, SpecialLeaveEligibilityRequest $eligibilityRequest, SpecialLeaveEligibilityService $service): RedirectResponse
    {
        $service->resubmit($request->user(), $eligibilityRequest, $request->validated(), $request->file('supporting_documents', []));

        return back()->with('status', 'Updated information resubmitted for HR review.');
    }

    public function withdraw(Request $request, SpecialLeaveEligibilityRequest $eligibilityRequest, SpecialLeaveEligibilityService $service): RedirectResponse
    {
        $service->withdraw($request->user(), $eligibilityRequest);

        return redirect()->route('employee.leave.eligibility.index')->with('status', 'Eligibility request withdrawn.');
    }

    public function document(Request $request, SpecialLeaveEligibilityDocument $document): StreamedResponse
    {
        Gate::authorize('view', $document->request);
        $bytes = Crypt::decryptString(Storage::disk('leave')->get($document->storage_path));

        return response()->streamDownload(fn () => print ($bytes), $document->original_name, ['Content-Type' => $document->mime_type, 'Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff']);
    }
}
