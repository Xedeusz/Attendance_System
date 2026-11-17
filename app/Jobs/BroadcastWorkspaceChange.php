<?php

namespace App\Jobs;

use App\Events\WorkspaceChanged;
use App\Models\User;
use App\Services\WorkspaceSync;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class BroadcastWorkspaceChange implements ShouldQueue
{
    use Queueable;

    public int $tries = 4;

    /** @param array<string, string> $revisions */
    public function __construct(public int $userId, public int $sessionVersion, public int $roleId, public array $revisions)
    {
        $this->onQueue('realtime');
    }

    public function backoff(): array
    {
        return [2, 10, 30];
    }

    public function handle(): void
    {
        $user = User::find($this->userId);
        if (! $user || ! $user->canAuthenticate() || $user->session_version !== $this->sessionVersion || $user->role_id !== $this->roleId) {
            return;
        }
        $revisions = array_intersect_key($this->revisions, array_flip(app(WorkspaceSync::class)->topics($user)));
        if ($revisions !== []) {
            broadcast(new WorkspaceChanged(WorkspaceSync::channel($user), $revisions));
        }
    }
}
