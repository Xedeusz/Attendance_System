<?php

namespace App\Policies;

use App\Models\AttendanceCorrectionRequest;
use App\Models\User;

class AttendanceCorrectionRequestPolicy
{
    public function view(User $user, AttendanceCorrectionRequest $correction): bool
    {
        return ! $user->isSupervisor() && $user->canAuthenticate() && ($user->isHr() || $correction->employee->user_id === $user->id);
    }

    public function review(User $user, AttendanceCorrectionRequest $correction): bool
    {
        return $user->isHr() && $user->canAuthenticate() && $correction->employee->user_id !== $user->id;
    }
}
