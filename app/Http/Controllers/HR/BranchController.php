<?php

namespace App\Http\Controllers\HR;

use App\Http\Controllers\Controller;
use App\Http\Requests\BranchRequest;
use App\Models\Branch;
use App\Services\Administration;
use App\Services\AuditService;
use App\Services\EditVersion;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class BranchController extends Controller
{
    public function index(Request $request): View
    {
        $data = $request->validate(['q' => ['nullable', 'string', 'max:100'], 'status' => ['nullable', Rule::in(['active', 'inactive'])], 'sort' => ['nullable', Rule::in(['branch_code', 'branch_name', 'created_at'])], 'direction' => ['nullable', Rule::in(['asc', 'desc'])]]);
        $branches = Branch::withCount('currentAssignments')->when($data['q'] ?? null, fn ($query, $q) => $query->where(fn ($query) => $query->where('branch_code', 'like', '%'.$q.'%')->orWhere('branch_name', 'like', '%'.$q.'%')->orWhere('city', 'like', '%'.$q.'%')))->when($data['status'] ?? null, fn ($query, $status) => $query->where('status', $status))->orderBy($data['sort'] ?? 'branch_name', $data['direction'] ?? 'asc')->orderBy('id')->paginate(15)->withQueryString();

        return view('hr.branches.index', compact('branches'));
    }

    public function create(): View
    {
        return view('hr.branches.form', ['branch' => new Branch(['status' => 'active'])]);
    }

    public function edit(Branch $branch): View
    {
        return view('hr.branches.form', compact('branch'));
    }

    public function show(Branch $branch): View
    {
        return view('hr.branches.show', ['branch' => $branch->loadCount('currentAssignments')]);
    }

    public function store(BranchRequest $request, Administration $administration, AuditService $audit): RedirectResponse
    {
        $branch = $administration->run(function () use ($request, $audit) {
            $branch = Branch::create($request->validated());
            $audit->record('branch.created', $branch, ['geofence' => ['previous' => null, 'current' => $branch->only(['latitude', 'longitude', 'geofence_radius_meters'])]]);

            return $branch;
        });

        return redirect()->route('hr.branches.show', $branch)->with('status', 'Branch created.');
    }

    public function update(BranchRequest $request, Branch $branch, Administration $administration, AuditService $audit): RedirectResponse
    {
        $administration->run(function () use ($request, $branch, $audit) {
            $branch->refresh();
            if ($request->validated('status') === 'inactive' && $branch->status === 'active') {
                $request->validate(['confirmation' => ['accepted']]);
                $count = $branch->currentAssignments()->whereHas('employee', fn ($employee) => $employee->where('employment_status', 'active'))->count();
                if ($count) {
                    throw ValidationException::withMessages(['status' => "Transfer or mark inactive the {$count} active employee assignments before deactivating this branch."]);
                }
            }
            $geofenceFields = ['latitude', 'longitude', 'geofence_radius_meters'];
            EditVersion::check($branch, $request->input('_edit_version'));
            $previousGeofence = $branch->only($geofenceFields);
            $branch->fill($request->validated());
            $changed = array_keys($branch->getDirty());
            $branch->save();
            $metadata = ['changed_fields' => $changed];
            if (array_intersect($changed, $geofenceFields)) {
                $metadata['geofence'] = ['previous' => $previousGeofence, 'current' => $branch->only($geofenceFields)];
            }
            $audit->record($branch->status === 'inactive' && in_array('status', $changed, true) ? 'branch.deactivated' : 'branch.updated', $branch, $metadata);
        });

        return redirect()->route('hr.branches.show', $branch)->with('status', 'Branch updated.');
    }
}
