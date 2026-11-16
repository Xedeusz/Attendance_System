<?php

namespace App\Policies;

use App\Models\LeaveRequest;
use App\Models\User;

class LeaveRequestPolicy
{
    public function view(User $user, LeaveRequest $leave): bool
    {
        return $user->canAuthenticate() && ($user->isHr() || $leave->employee->user_id === $user->id || ($user->isSupervisor() && LeaveRequest::whereKey($leave->id)->forSupervisor($user)->exists()));
    }

    public function review(User $user, LeaveRequest $leave): bool
    {
        return $user->isSupervisor() && $user->canAuthenticate() && $leave->employee->user_id !== $user->id && LeaveRequest::whereKey($leave->id)->forSupervisor($user)->exists();
    }

    public function cancel(User $user, LeaveRequest $leave): bool
    {
        return $user->canAuthenticate() && $leave->employee->user_id === $user->id;
    }
}
