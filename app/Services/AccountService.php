<?php

namespace App\Services;

use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AccountService
{
    public function __construct(private InvitationService $invitations, private AuditService $audit) {}

    public function invalidateSessions(User $user): void
    {
        $user->forceFill(['session_version' => $user->session_version + 1, 'remember_token' => Str::random(60)])->save();
        DB::table('sessions')->where('user_id', $user->id)->delete();
        DB::table('password_reset_tokens')->where('email', $user->email)->delete();
    }

    public function deactivate(User $user): void
    {
        if ($user->isHr() && $user->account_status === 'active' && User::where('role_id', Role::HR)->where('account_status', 'active')->count() <= 1) {
            throw ValidationException::withMessages(['account' => 'The last active HR account cannot be deactivated.']);
        }
        $user->forceFill(['account_status' => 'deactivated'])->save();
        $this->invalidateSessions($user);
        $this->invitations->revoke($user);
        $this->audit->record($user->isHr() ? 'hr.deactivated' : 'employee.deactivated', $user);
    }

    public function reactivate(User $user): ?array
    {
        if ($user->account_status !== 'deactivated' || ($user->employee && $user->employee->employment_status !== 'active')) {
            throw ValidationException::withMessages(['account' => 'Only a deactivated account with eligible employment can be reactivated.']);
        }
        $user->forceFill(['account_status' => $user->email_verified_at ? 'active' : 'pending_activation'])->save();
        $this->invalidateSessions($user);
        $this->audit->record($user->isHr() ? 'hr.reactivated' : 'employee.reactivated', $user, ['account_status' => $user->account_status]);

        return $user->account_status === 'pending_activation' ? $this->invitations->issue($user) : null;
    }
}
