<?php

namespace App\Http\Controllers;

use App\Models\AttendanceEvent;
use App\Services\AttendanceService;
use App\Services\WorkspaceSync;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class WorkspaceSyncController extends Controller
{
    public function __invoke(Request $request, WorkspaceSync $sync, AttendanceService $attendance): JsonResponse
    {
        $user = $request->user();
        $data = [
            'channel' => WorkspaceSync::channel($user),
            'user_name' => $user->name,
            'business_date' => now(config('attendance.timezone'))->toDateString(),
            'revisions' => DB::table('workspace_revisions')->where('user_id', $user->id)->whereIn('topic', $sync->topics($user))->pluck('revision', 'topic'),
            'unread' => $user->unreadNotifications()->when($user->isSupervisor(), fn ($query) => $query->where('data->category', 'leave'))->count(),
        ];
        if (! $user->isSupervisor() && $user->employee && $request->boolean('attendance')) {
            $data['attendance'] = $attendance->availability($user->employee);
            $latest = AttendanceEvent::where('employee_id', $user->employee->id)->latest('id')->first();
            $data['attendance']['latestResult'] = $latest ? $attendance->result($latest) : null;
        }

        return response()->json($data)->header('Cache-Control', 'no-store, private');
    }
}
