<?php

namespace App\Events;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;

class WorkspaceChanged implements ShouldBroadcastNow
{
    /** @param array<string, string> $revisions */
    public function __construct(public string $channel, public array $revisions) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel($this->channel)];
    }

    public function broadcastAs(): string
    {
        return 'workspace.changed';
    }

    public function broadcastWith(): array
    {
        return ['revisions' => $this->revisions];
    }
}
