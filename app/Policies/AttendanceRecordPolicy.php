<?php

namespace App\Policies;

use App\Models\AttendanceRecord;
use App\Models\User;

class AttendanceRecordPolicy
{
    public function view(User $user, AttendanceRecord $record): bool
    {
        return ! $user->isSupervisor() && ($user->isHr() || $user->employee?->id === $record->employee_id);
    }
}
