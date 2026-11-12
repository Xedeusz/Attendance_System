<?php

namespace App\Http\Controllers\HR;

use App\Http\Controllers\Controller;
use App\Http\Requests\EmployeeRequest;
use App\Models\AccountInvitation;
use App\Models\Branch;
use App\Models\Department;
use App\Models\Employee;
use App\Services\AccountService;
use App\Services\Administration;
use App\Services\EmployeeService;
use App\Services\InvitationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class EmployeeController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', Employee::class);
        $filters = $request->validate(['q' => ['nullable', 'string', 'max:100'], 'branch_id' => ['nullable', 'integer', 'exists:branches,id'], 'department_id' => ['nullable', 'integer', 'exists:departments,id'], 'employment_status' => ['nullable', Rule::in(['active', 'inactive'])], 'account_status' => ['nullable', Rule::in(['active', 'pending_activation', 'deactivated'])], 'sort' => ['nullable', Rule::in(['employee_number', 'last_name', 'created_at'])], 'direction' => ['nullable', Rule::in(['asc', 'desc'])]]);
        $employees = Employee::with(['user', 'department', 'currentAssignment.branch'])
            ->when($filters['q'] ?? null, fn ($query, $q) => $query->where(fn ($query) => $query->where('employee_number', 'like', '%'.$q.'%')->orWhere('first_name', 'like', '%'.$q.'%')->orWhere('last_name', 'like', '%'.$q.'%')->orWhereHas('user', fn ($user) => $user->where('email', 'like', '%'.$q.'%'))))
            ->when($filters['branch_id'] ?? null, fn ($query, $id) => $query->whereHas('currentAssignment', fn ($assignment) => $assignment->where('branch_id', $id)))
            ->when($filters['department_id'] ?? null, fn ($query, $id) => $query->where('department_id', $id))
            ->when($filters['employment_status'] ?? null, fn ($query, $status) => $query->where('employment_status', $status))
            ->when($filters['account_status'] ?? null, fn ($query, $status) => $query->whereHas('user', fn ($user) => $user->where('account_status', $status)))
            ->orderBy($filters['sort'] ?? 'created_at', $filters['direction'] ?? 'desc')->orderBy('id')->paginate(15)->withQueryString();

        return view('hr.employees.index', ['employees' => $employees, 'branches' => Branch::orderBy('branch_name')->get(), 'departments' => Department::orderBy('department_name')->get()]);
    }

    public function create(): View
    {
        Gate::authorize('create', Employee::class);

        return $this->form(new Employee);
    }

    public function edit(Employee $employee): View
    {
        Gate::authorize('update', $employee);

        return $this->form($employee->load(['user', 'currentAssignment']));
    }

    private function form(Employee $employee): View
    {
        return view('hr.employees.form', ['employee' => $employee, 'branches' => Branch::where('status', 'active')->orderBy('branch_name')->get(), 'departments' => Department::where('status', 'active')->orderBy('department_name')->get()]);
    }

    public function show(Employee $employee): View
    {
        Gate::authorize('view', $employee);

        return view('hr.employees.show', ['employee' => $employee->load(['user', 'department', 'currentAssignment.branch', 'assignments.branch']), 'invitation' => $employee->user->id ? AccountInvitation::where('user_id', $employee->user_id)->latest('id')->first() : null]);
    }

    public function store(EmployeeRequest $request, EmployeeService $service, InvitationService $invitations): RedirectResponse
    {
        $result = $service->save($request->validated());

        return redirect()->route('hr.employees.show', $result['employee'])->with('status', $invitations->deliver($result['issued']));
    }

    public function update(EmployeeRequest $request, Employee $employee, EmployeeService $service, InvitationService $invitations): RedirectResponse
    {
        Gate::authorize('update', $employee);
        $result = $service->save($request->validated(), $employee);

        return redirect()->route('hr.employees.show', $employee)->with('status', $result['issued'] ? $invitations->deliver($result['issued']) : 'Employee information updated. Existing password was preserved.');
    }

    public function status(Request $request, Employee $employee, Administration $administration, AccountService $accounts, InvitationService $invitations): RedirectResponse
    {
        Gate::authorize('update', $employee);
        $data = $request->validate(['action' => ['required', Rule::in(['deactivate', 'reactivate'])], 'confirmation' => ['accepted']]);
        $issued = $administration->run(function () use ($employee, $accounts, $data) {
            $user = $employee->user()->lockForUpdate()->firstOrFail();
            if ($data['action'] === 'deactivate') {
                $accounts->deactivate($user);

                return null;
            }

            return $accounts->reactivate($user);
        });

        return back()->with('status', $issued ? $invitations->deliver($issued) : 'Account status updated.');
    }

    public function resend(Employee $employee, Administration $administration, InvitationService $invitations): RedirectResponse
    {
        Gate::authorize('update', $employee);
        $issued = $administration->run(fn () => $invitations->issue($employee->user));

        return back()->with('status', $invitations->deliver($issued));
    }
}
