<?php

namespace App\Http\Controllers;

use App\Services\AuditService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class SelfServiceController extends Controller
{
    public function profile(Request $request): View
    {
        return view('employee.profile', ['user' => $request->user()->load(['employee.department', 'employee.currentAssignment.branch'])]);
    }

    public function update(Request $request, AuditService $audit): RedirectResponse
    {
        $employee = $request->user()->employee;
        abort_unless($employee, 403);
        $data = $request->validate(['contact_number' => ['nullable', 'string', 'max:30', 'regex:/^\+?[0-9 ()-]{7,30}$/'], 'role' => ['prohibited'], 'role_id' => ['prohibited'], 'employee_number' => ['prohibited'], 'department_id' => ['prohibited'], 'position_title' => ['prohibited'], 'branch_id' => ['prohibited'], 'employment_status' => ['prohibited'], 'account_status' => ['prohibited'], 'user_id' => ['prohibited'], 'email' => ['prohibited'], 'work_email' => ['prohibited']]);
        DB::transaction(function () use ($employee, $data, $audit) {
            $employee->update(['contact_number' => $data['contact_number'] ?? null]);
            $audit->record('profile.contact_updated', $employee);
        });

        return back()->with('status', 'Your contact number was updated.');
    }

    public function settings(): View
    {
        return view('workspace.settings');
    }
}
