<?php

namespace App\Http\Controllers\HR;

use App\Http\Controllers\Controller;
use App\Models\AttendanceAssistanceRequest;
use App\Models\AttendanceEvent;
use App\Services\Administration;
use App\Services\AttendanceService;
use App\Services\AuditService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AttendanceReviewController extends Controller
{
    public function store(Request $request, AttendanceEvent $event, AttendanceService $attendance): RedirectResponse
    {
        $data = $request->validate(['decision' => ['required', Rule::in(['approved', 'rejected'])], 'remarks' => ['required', 'string', 'min:10', 'max:1000'], 'confirmation' => ['accepted']]);
        $attendance->review($request->user(), $event, $data['decision'], $data['remarks']);

        return back()->with('status', 'Review decision saved. Original evidence and server timestamps were preserved.');
    }

    public function assistance(Request $request, AttendanceAssistanceRequest $assistance, Administration $administration, AuditService $audit): RedirectResponse
    {
        $data = $request->validate(['status' => ['required', Rule::in(['resolved', 'rejected'])], 'remarks' => ['required', 'string', 'min:10', 'max:1000'], 'confirmation' => ['accepted']]);
        $administration->run(function () use ($request, $assistance, $data, $audit) {
            $assistance->refresh();
            abort_unless($assistance->status === 'pending', 409);
            abort_if($assistance->employee->user_id === $request->user()->id, 403);
            $assistance->update([...collect($data)->only(['status', 'remarks'])->all(), 'reviewer_user_id' => $request->user()->id, 'reviewed_at' => now()]);
            $audit->record('attendance.assistance_'.$data['status'], $assistance);
        });

        return back()->with('status', 'Assistance disposition recorded. No attendance timestamps were created or changed.');
    }
}
