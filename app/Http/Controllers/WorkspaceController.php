<?php

namespace App\Http\Controllers;

use App\Models\AttendanceEvent;
use App\Services\AttendanceQueries;
use Illuminate\Http\Request;
use Illuminate\View\View;

class WorkspaceController extends Controller
{
    public function notifications(Request $request, AttendanceQueries $queries): View
    {
        $hr = $request->user()->isHr();
        $events = AttendanceEvent::with(['record.employee', 'review'])->when($hr, fn ($query) => $query->where('review_status', 'pending'), fn ($query) => $query->where('employee_id', $request->user()->employee?->id ?? 0)->whereIn('review_status', ['pending', 'approved', 'rejected']))->latest('updated_at')->paginate(15);

        return view('attendance.updates', ['events' => $events, 'hr' => $hr, 'stats' => $hr ? $queries->statistics() : [], 'notices' => $request->user()->notifications()->latest()->paginate(10, ['*'], 'notifications_page')]);
    }

    public function module(string $module): View
    {
        abort_unless(in_array($module, ['leave', 'reports'], true), 404);

        return view('workspace.module', ['module' => $module, 'definition' => config('workspace.modules.'.$module)]);
    }
}
