<?php

namespace App\Notifications;

use Illuminate\Notifications\Notification;

class WorkflowNotice extends Notification
{
    public function __construct(public string $category, public string $title, public string $url, public int $recordId) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return ['category' => $this->category, 'title' => $this->title, 'url' => $this->url, 'record_id' => $this->recordId];
    }
}
