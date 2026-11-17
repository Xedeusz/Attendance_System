<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\LeaveRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class EmployeeProfileController extends Controller
{
    public function show(Request $request, Employee $employee): View
    {
        abort_unless($request->user()->isSupervisor(), 403);
        Gate::authorize('view', $employee);
        $employee->load(['user', 'department', 'currentAssignment.branch']);
        $returnLeave = null;
        if ($request->filled('leave')) {
            $returnLeave = LeaveRequest::findOrFail($request->integer('leave'));
            abort_unless($returnLeave->employee_id === $employee->id, 403);
            Gate::authorize('review', $returnLeave);
        }

        return view('employee.view-profile', compact('employee', 'returnLeave'));
    }
}
