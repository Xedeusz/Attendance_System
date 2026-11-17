<?php

namespace App\Policies;

use App\Models\Employee;
use App\Models\SupervisorAssignment;
use App\Models\User;

class EmployeePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isHr();
    }

    public function create(User $user): bool
    {
        return $user->isHr();
    }

    public function view(User $user, Employee $employee): bool
    {
        return $user->isHr() || $employee->user_id === $user->id || ($user->isSupervisor() && SupervisorAssignment::current()->whereNull('revoked_at')->where('supervisor_user_id', $user->id)->where('employee_id', $employee->id)->exists());
    }

    public function update(User $user, Employee $employee): bool
    {
        return $user->isHr();
    }
}
