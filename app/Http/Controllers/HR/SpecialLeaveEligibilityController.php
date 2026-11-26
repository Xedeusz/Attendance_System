<?php

namespace App\Http\Controllers\HR;

use App\Http\Controllers\Controller;
use App\Http\Requests\ReviewSpecialLeaveEligibilityRequest;
use App\Models\LeaveType;
use App\Models\SpecialLeaveEligibilityRequest;
use App\Services\SpecialLeaveEligibilityService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class SpecialLeaveEligibilityController extends Controller
{
    public function index(Request $request): View
    {
        $data = $request->validate(['q' => ['nullable', 'string', 'max:100'], 'status' => ['nullable', Rule::in(SpecialLeaveEligibilityRequest::STATUSES)], 'leave_type_id' => ['nullable', 'integer', 'exists:leave_types,id']]);
        $query = SpecialLeaveEligibilityRequest::with(['employee.department', 'employee.currentAssignment.branch', 'type'])
            ->when($data['q'] ?? null, fn ($builder, $term) => $builder->whereHas('employee', fn ($employee) => $employee->where(fn ($names) => $names->where('first_name', 'like', '%'.$term.'%')->orWhere('last_name', 'like', '%'.$term.'%')->orWhere('employee_number', 'like', '%'.$term.'%'))))
            ->when($data['status'] ?? null, fn ($builder, $status) => $builder->where('status', $status))
            ->when($data['leave_type_id'] ?? null, fn ($builder, $type) => $builder->where('leave_type_id', $type));

        return view('hr.leave.eligibility.index', ['requests' => $query->latest('submitted_at')->latest('id')->paginate(15)->withQueryString(), 'types' => LeaveType::whereIn('code', ['MATERNITY', 'PATERNITY'])->pluck('name', 'id'), 'stats' => SpecialLeaveEligibilityRequest::selectRaw('status, count(*) total')->groupBy('status')->pluck('total', 'status')]);
    }

    public function show(SpecialLeaveEligibilityRequest $eligibilityRequest): View
    {
        return view('hr.leave.eligibility.show', ['eligibilityRequest' => $eligibilityRequest->load(['employee.department', 'employee.currentAssignment.branch', 'type', 'documents.uploader', 'activities.actor', 'entitlement'])]);
    }

    public function review(ReviewSpecialLeaveEligibilityRequest $request, SpecialLeaveEligibilityRequest $eligibilityRequest, SpecialLeaveEligibilityService $service): RedirectResponse
    {
        $service->review($request->user(), $eligibilityRequest, $request->validated());

        return back()->with('status', 'Eligibility request updated.');
    }
}
