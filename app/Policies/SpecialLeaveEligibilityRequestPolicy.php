<?php

namespace App\Policies;

use App\Models\SpecialLeaveEligibilityRequest;
use App\Models\User;

class SpecialLeaveEligibilityRequestPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->canAuthenticate();
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, SpecialLeaveEligibilityRequest $specialLeaveEligibilityRequest): bool
    {
        return $user->canAuthenticate() && ($user->isHr() || $specialLeaveEligibilityRequest->employee->user_id === $user->id);
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->canAuthenticate() && (bool) $user->employee;
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, SpecialLeaveEligibilityRequest $specialLeaveEligibilityRequest): bool
    {
        return $user->canAuthenticate() && $specialLeaveEligibilityRequest->employee->user_id === $user->id && $specialLeaveEligibilityRequest->status === 'additional_information_required';
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, SpecialLeaveEligibilityRequest $specialLeaveEligibilityRequest): bool
    {
        return $user->canAuthenticate() && $specialLeaveEligibilityRequest->employee->user_id === $user->id && in_array($specialLeaveEligibilityRequest->status, ['pending_hr_review', 'additional_information_required'], true);
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, SpecialLeaveEligibilityRequest $specialLeaveEligibilityRequest): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, SpecialLeaveEligibilityRequest $specialLeaveEligibilityRequest): bool
    {
        return false;
    }
}
