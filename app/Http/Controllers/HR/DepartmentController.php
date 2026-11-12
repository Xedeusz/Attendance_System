<?php

namespace App\Http\Controllers\HR;

use App\Http\Controllers\Controller;
use App\Http\Requests\DepartmentRequest;
use App\Models\Department;
use App\Services\Administration;
use App\Services\AuditService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class DepartmentController extends Controller
{
    public function index(Request $request): View
    {
        $data = $request->validate(['q' => ['nullable', 'string', 'max:100'], 'status' => ['nullable', Rule::in(['active', 'inactive'])], 'sort' => ['nullable', Rule::in(['department_code', 'department_name'])], 'direction' => ['nullable', Rule::in(['asc', 'desc'])]]);
        $departments = Department::withCount('employees')->when($data['q'] ?? null, fn ($query, $q) => $query->where(fn ($query) => $query->where('department_code', 'like', '%'.$q.'%')->orWhere('department_name', 'like', '%'.$q.'%')))->when($data['status'] ?? null, fn ($query, $status) => $query->where('status', $status))->orderBy($data['sort'] ?? 'department_name', $data['direction'] ?? 'asc')->orderBy('id')->paginate(15)->withQueryString();

        return view('hr.departments.index', compact('departments'));
    }

    public function create(): View
    {
        return view('hr.departments.form', ['department' => new Department(['status' => 'active'])]);
    }

    public function edit(Department $department): View
    {
        return view('hr.departments.form', compact('department'));
    }

    public function store(DepartmentRequest $request, Administration $administration, AuditService $audit): RedirectResponse
    {
        $administration->run(function () use ($request, $audit) {
            $department = Department::create($request->validated());
            $audit->record('department.created', $department);
        });

        return redirect()->route('hr.departments.index')->with('status', 'Department created.');
    }

    public function update(DepartmentRequest $request, Department $department, Administration $administration, AuditService $audit): RedirectResponse
    {
        $administration->run(function () use ($request, $department, $audit) {
            $department->refresh();
            if ($request->validated('status') === 'inactive' && $department->status === 'active') {
                $request->validate(['confirmation' => ['accepted']]);
                if ($department->employees()->where('employment_status', 'active')->exists()) {
                    throw ValidationException::withMessages(['status' => 'Reassign active employees before deactivating this department.']);
                }
            }
            $department->fill($request->validated());
            $changed = array_keys($department->getDirty());
            $department->save();
            $audit->record($department->status === 'inactive' && in_array('status', $changed, true) ? 'department.deactivated' : 'department.updated', $department, ['changed_fields' => $changed]);
        });

        return redirect()->route('hr.departments.index')->with('status', 'Department updated.');
    }
}
