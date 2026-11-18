<?php

namespace App\Http\Controllers;

use App\Models\AttendanceCorrectionRequest;
use App\Models\LeaveRequest;
use App\Models\User;
use App\Services\WorkspaceSync;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Str;

class NotificationDropdownController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $items = $user->workspaceNotifications()->latest()->orderByDesc('id')->limit(10)->get()
            ->map(fn (DatabaseNotification $notice): array => [
                'id' => $notice->id,
                'title' => Str::limit((string) ($notice->data['title'] ?? 'System notification'), 180),
                'message' => Str::limit((string) ($notice->data['message'] ?? ''), 240),
                'category' => $notice->data['category'] ?? null,
                'time' => $notice->created_at->diffForHumans(),
                'datetime' => $notice->created_at->toIso8601String(),
                'unread' => $notice->read_at === null,
                'can_open' => isset($notice->data['url']),
                'read_url' => route('notifications.dropdown.read', $notice->id, false),
            ]);

        return response()->json(['items' => $items, 'unread' => $user->workspaceNotifications()->unread()->count()]);
    }

    public function read(Request $request, string $notice, WorkspaceSync $sync): JsonResponse|RedirectResponse
    {
        $user = $request->user();
        $notification = $user->workspaceNotifications()->whereKey($notice)->firstOrFail();
        if ($user->workspaceNotifications()->whereKey($notice)->unread()->update(['read_at' => now()])) {
            $sync->touch($user, ['notifications']);
        }
        $open = $request->boolean('open');
        $destination = $open ? $this->destination($user, $notification) : null;
        $message = $open && ! $destination ? 'This notification’s destination is no longer available.' : null;

        if (! $request->expectsJson()) {
            return $destination ? redirect($destination) : back()->with('status', $message ?? 'Notification marked as read.');
        }

        return response()->json([
            'unread' => $user->workspaceNotifications()->unread()->count(),
            'destination' => $destination,
            'message' => $message,
        ]);
    }

    public function readAll(Request $request, WorkspaceSync $sync): JsonResponse
    {
        $user = $request->user();
        if ($user->workspaceNotifications()->unread()->update(['read_at' => now()])) {
            $sync->touch($user, ['notifications']);
        }

        return response()->json(['unread' => $user->workspaceNotifications()->unread()->count()]);
    }

    private function destination(User $user, DatabaseNotification $notice): ?string
    {
        $data = $notice->data;
        $recordId = filter_var($data['record_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        $path = isset($data['url']) && is_string($data['url']) ? parse_url($data['url'], PHP_URL_PATH) : null;
        if (! $recordId || ! $path) {
            return null;
        }
        if (($data['category'] ?? null) === 'leave') {
            if ($user->isSupervisor() && $path === route('supervisor.leave.index', absolute: false)) {
                return route('supervisor.leave.index', absolute: false);
            }

            $leave = LeaveRequest::find($recordId);
            if (! $leave) {
                return null;
            }
            if ($path === route('employee.leave.show', $leave, false)) {
                return $leave->employee->user_id === $user->id && $user->can('view', $leave) ? $path : null;
            }
            if (! ($user->isHr() || $user->isSupervisor()) || ! $user->can($user->isSupervisor() ? 'review' : 'view', $leave)) {
                return null;
            }
            $scope = $user->isHr() ? 'hr' : 'supervisor';
            $target = route($scope.'.leave.show', $leave, false);

            return $path === $target ? $target : null;
        }
        if (($data['category'] ?? null) === 'correction' && ! $user->isSupervisor()) {
            $correction = AttendanceCorrectionRequest::find($recordId);
            if ($correction && $user->can('view', $correction)) {
                $target = route(($user->isHr() ? 'hr' : 'employee').'.corrections.show', $correction, false);

                return $path === $target ? $target : null;
            }
        }

        return null;
    }
}
