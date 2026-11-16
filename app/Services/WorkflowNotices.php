<?php

namespace App\Services;

use App\Models\Role;
use App\Models\User;
use App\Notifications\WorkflowNotice;

class WorkflowNotices
{
    public function send(User $user, string $category, string $title, string $url, int $id): void
    {
        $user->notify(new WorkflowNotice($category, $title, $url, $id));
    }

    public function hr(string $category, string $title, string $url, int $id): void
    {
        foreach (User::where('role_id', Role::HR)->where('account_status', 'active')->get() as $user) {
            $this->send($user, $category, $title, $url, $id);
        }
    }
}
