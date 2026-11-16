<?php

namespace App\Http\Controllers\HR;

use App\Http\Controllers\Controller;
use App\Http\Requests\SupervisorAccountRequest;
use App\Http\Requests\SupervisorAssignmentRequest;
use App\Models\Employee;
use App\Models\Role;
use App\Models\SupervisorAssignment;
use App\Models\User;
use App\Services\AccountService;
use App\Services\Administration;
use App\Services\InvitationService;
use App\Services\LeaveService;
use App\Services\SupervisorService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class SupervisorController extends Controller
{
    public function index(Request $request): View
    {
        $data = $request->validate(['q' => ['nullable', 'string', 'max:100'], 'status' => ['nullable', Rule::in(['active', 'pending_activation', 'deactivated'])]]);
        $users = User::where('role_id', Role::SUPERVISOR)->with('employee.department')->when($data['q'] ?? null, fn ($q, $term) => $q->where(fn ($q) => $q->where('name', 'like', '%'.$term.'%')->orWhere('email', 'like', '%'.$term.'%')))->when($data['status'] ?? null, fn ($q, $status) => $q->where('account_status', $status))->orderBy('name')->paginate(15)->withQueryString();
        $counts = SupervisorAssignment::current()->whereIn('supervisor_user_id', $users->pluck('id'))->selectRaw('supervisor_user_id,COUNT(*) AS total')->groupBy('supervisor_user_id')->pluck('total', 'supervisor_user_id');

        return view('hr.supervisors.index', compact('users', 'counts'));
    }

    public function create(): View
    {
        return view('hr.supervisors.form', ['supervisor' => new User, 'employees' => Employee::with('user')->whereHas('user', fn ($q) => $q->where('role_id', Role::EMPLOYEE))->orderBy('last_name')->get()]);
    }

    public function edit(User $supervisor): View
    {
        abort_unless($supervisor->isSupervisor(), 404);

        return view('hr.supervisors.form', ['supervisor' => $supervisor->load('employee'), 'employees' => collect()]);
    }

    public function store(SupervisorAccountRequest $request, SupervisorService $service, InvitationService $invitations): RedirectResponse
    {
        $result = $service->save($request->user(), $request->validated());

        return redirect()->route('hr.supervisors.show', $result['user'])->with('status', $result['issued'] ? $invitations->deliver($result['issued']) : 'Supervisor account saved. Existing identity and history retained.');
    }

    public function update(SupervisorAccountRequest $request, User $supervisor, SupervisorService $service, InvitationService $invitations): RedirectResponse
    {
        $result = $service->save($request->user(), $request->validated(), $supervisor);

        return redirect()->route('hr.supervisors.show', $result['user'])->with('status', $result['issued'] ? $invitations->deliver($result['issued']) : 'Supervisor account updated.');
    }

    public function show(User $supervisor): View
    {
        abort_unless($supervisor->isSupervisor(), 404);

        return view('hr.supervisors.show', ['supervisor' => $supervisor, 'assignments' => SupervisorAssignment::with(['employee.user', 'employee.department', 'employee.currentAssignment.branch'])->where('supervisor_user_id', $supervisor->id)->latest('id')->paginate(15), 'employees' => Employee::with('user')->where('employment_status', 'active')->where('user_id', '!=', $supervisor->id)->orderBy('last_name')->get()]);
    }

    public function assign(SupervisorAssignmentRequest $request, User $supervisor, SupervisorService $service): RedirectResponse
    {
        $service->assign($request->user(), $supervisor, $request->validated());

        return back()->with('status', 'Supervisor assignment saved; pending leave routing updated.');
    }

    public function revoke(Request $request, User $supervisor, SupervisorAssignment $assignment, SupervisorService $service): RedirectResponse
    {
        $request->validate(['confirmation' => ['accepted']]);
        $service->revoke($request->user(), $supervisor, $assignment);

        return back()->with('status', 'Supervisor assignment revoked.');
    }

    public function status(Request $request, User $supervisor, Administration $administration, AccountService $accounts, InvitationService $invitations, LeaveService $leave): RedirectResponse
    {
        abort_unless($supervisor->isSupervisor(), 404);
        $data = $request->validate(['action' => ['required', Rule::in(['deactivate', 'reactivate'])], 'confirmation' => ['accepted']]);
        $issued = $administration->run(function () use ($supervisor, $data, $accounts, $leave) {
            $user = $supervisor->fresh();
            if ($data['action'] === 'deactivate') {
                $accounts->deactivate($user);
                $issued = null;
            } else {
                $issued = $accounts->reactivate($user);
            }$leave->refreshRouting();

            return $issued;
        });

        return back()->with('status', $issued ? $invitations->deliver($issued) : 'Supervisor account status updated.');
    }

    public function resend(User $supervisor, Administration $administration, InvitationService $invitations): RedirectResponse
    {
        abort_unless($supervisor->isSupervisor(), 404);
        $issued = $administration->run(fn () => $invitations->issue($supervisor->fresh()));

        return back()->with('status', $invitations->deliver($issued));
    }
}
