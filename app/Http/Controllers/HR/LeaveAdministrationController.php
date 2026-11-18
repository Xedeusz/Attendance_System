<?php

namespace App\Http\Controllers\HR;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Department;
use App\Models\Employee;
use App\Models\EmployeeLeaveBalance;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\Role;
use App\Models\User;
use App\Services\Administration;
use App\Services\AuditService;
use App\Services\EditVersion;
use App\Services\LeaveService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class LeaveAdministrationController extends Controller
{
    public function index(Request $request): View
    {
        $data = $request->validate(['leave_type_id' => ['nullable', 'integer', 'exists:leave_types,id'], 'q' => ['nullable', 'string', 'max:100'], 'branch_id' => ['nullable', 'integer', 'exists:branches,id'], 'department_id' => ['nullable', 'integer', 'exists:departments,id'], 'supervisor_id' => ['nullable', 'integer', 'exists:users,id'], 'status' => ['nullable', Rule::in([...LeaveRequest::PENDING, 'approved', 'rejected', 'cancelled', 'revoked'])], 'from' => ['nullable', 'date_format:Y-m-d'], 'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from']]);
        $query = LeaveRequest::with(['employee.user', 'employee.department', 'employee.currentAssignment.branch', 'type', 'supervisor'])->when($data['leave_type_id'] ?? null, fn ($q, $id) => $q->where('leave_type_id', $id))->when($data['q'] ?? null, fn ($q, $term) => $q->whereHas('employee', fn ($q) => $q->where(fn ($q) => $q->where('first_name', 'like', '%'.$term.'%')->orWhere('last_name', 'like', '%'.$term.'%')->orWhere('employee_number', 'like', '%'.$term.'%'))))->when($data['branch_id'] ?? null, fn ($q, $id) => $q->whereHas('employee.currentAssignment', fn ($q) => $q->where('branch_id', $id)))->when($data['department_id'] ?? null, fn ($q, $id) => $q->whereHas('employee', fn ($q) => $q->where('department_id', $id)))->when($data['supervisor_id'] ?? null, fn ($q, $id) => $q->where('assigned_supervisor_user_id', $id))->when($data['status'] ?? null, fn ($q, $status) => $q->where('status', $status))->when($data['from'] ?? null, fn ($q, $date) => $q->whereDate('end_date', '>=', $date))->when($data['to'] ?? null, fn ($q, $date) => $q->whereDate('start_date', '<=', $date));

        return view('leave.index', ['scope' => 'hr', 'leaves' => $query->latest('id')->paginate(15)->withQueryString(), 'stats' => LeaveRequest::selectRaw('status,COUNT(*) AS total')->groupBy('status')->pluck('total', 'status')->all(), 'balances' => collect(), 'branches' => Branch::orderBy('branch_name')->get(), 'departments' => Department::orderBy('department_name')->get(), 'supervisors' => User::where('role_id', Role::SUPERVISOR)->orderBy('name')->get()]);
    }

    public function types(Request $request): View
    {
        return view('hr.leave.types', ['types' => LeaveType::withCount('requests')->orderBy('name')->paginate(20), 'editing' => $request->filled('edit') ? LeaveType::findOrFail($request->integer('edit')) : new LeaveType]);
    }

    public function saveType(Request $request, Administration $administration, AuditService $audit, ?LeaveType $type = null): RedirectResponse
    {
        $data = $request->validate(['code' => ['required', 'string', 'max:30', 'regex:/^[A-Za-z0-9_-]+$/', Rule::unique('leave_types')->ignore($type?->id)], 'name' => ['required', 'string', 'max:150'], 'description' => ['nullable', 'string', 'max:2000'], 'requires_document' => ['required', 'boolean'], 'affects_balance' => ['required', 'boolean'], 'allows_half_day' => ['required', 'boolean'], 'is_active' => ['required', 'boolean'], 'duration_policy' => ['required', Rule::in(['requested_days'])]]);
        $administration->run(function () use ($type, $data, $audit) {
            if ($type) {
                $type->refresh();
                EditVersion::check($type, request()->input('_edit_version'));
            }
            $type ??= new LeaveType;
            $type->fill($data)->save();
            $audit->record('leave.type_saved', $type, ['code' => $type->code, 'affects_balance' => $type->affects_balance]);
        });

        return redirect()->route('hr.leave.types')->with('status', 'Leave type saved.');
    }

    public function balances(Request $request): View
    {
        $data = $request->validate(['employee_id' => ['nullable', 'integer', 'exists:employees,id']]);

        return view('hr.leave.balances', ['balances' => EmployeeLeaveBalance::with(['employee.user', 'type', 'transactions.authorizer'])->when($data['employee_id'] ?? null, fn ($q, $id) => $q->where('employee_id', $id))->latest('id')->paginate(15), 'employees' => Employee::with('user')->orderBy('last_name')->get(), 'types' => LeaveType::orderBy('name')->get()]);
    }

    public function adjust(Request $request, LeaveService $leave): RedirectResponse
    {
        $data = $request->validate(['employee_id' => ['required', 'integer', 'exists:employees,id'], 'leave_type_id' => ['required', 'integer', 'exists:leave_types,id'], 'policy_period' => ['required', 'regex:/^20[0-9]{2}$/'], 'amount' => ['required', 'numeric', 'min:-9999', 'max:9999', 'multiple_of:0.5', 'not_in:0'], 'reason' => ['required', 'string', 'min:10', 'max:1000'], 'confirmation' => ['accepted']]);
        $leave->adjustBalance($request->user(), $data);

        return back()->with('status', 'Leave balance adjustment recorded.');
    }

    public function route(Request $request, LeaveRequest $leave, Administration $administration, LeaveService $service): RedirectResponse
    {
        $request->validate(['confirmation' => ['accepted']]);
        $administration->run(fn () => $service->refreshRouting($leave->employee_id));

        return back()->with('status', 'Pending leave routing checked against current assignments.');
    }
}
