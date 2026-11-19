<?php

namespace App\Services;

use App\Mail\AccountInvitationMail;
use App\Models\AccountInvitation;
use App\Models\LocalMailMessage;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

class InvitationService
{
    public function __construct(private AuditService $audit, private EssentialEmailOutbox $emails) {}

    public function revoke(User $user): void
    {
        $invitations = AccountInvitation::where('user_id', $user->id)->whereNull('used_at')->whereNull('revoked_at')->get();
        foreach ($invitations as $invitation) {
            $invitation->update(['revoked_at' => now()]);
            $this->audit->record('invitation.revoked', $invitation);
        }
        // Local messages are short-lived encrypted development artifacts, not an archive.
        if (app()->environment('local')) {
            LocalMailMessage::where('recipient_hash', hash('sha256', $user->email))->delete();
        }
    }

    /** @return array{invitation: AccountInvitation, token: string} */
    public function issue(User $user): array
    {
        if ($user->account_status !== 'pending_activation') {
            throw ValidationException::withMessages(['account' => 'Only pending accounts can receive activation invitations.']);
        }
        $this->revoke($user);
        DB::table('password_reset_tokens')->where('email', $user->email)->delete();
        $token = bin2hex(random_bytes(32));
        $invitation = AccountInvitation::create(['user_id' => $user->id, 'token_hash' => hash('sha256', $token), 'expires_at' => now()->addHours(24)]);
        $this->audit->record('invitation.issued', $invitation, ['expires_in_hours' => 24]);
        if ($this->emails->enabled()) {
            $this->emails->accountSetup($invitation, $token);
        }

        return compact('invitation', 'token');
    }

    public function deliver(#[\SensitiveParameter] array $issued): string
    {
        $invitation = $issued['invitation']->fresh();
        if ($invitation->revoked_at || $invitation->used_at || $invitation->user->account_status !== 'pending_activation') {
            return 'The account was saved. The invitation is no longer eligible for delivery.';
        }
        $mailer = config('mail.default');
        if (! in_array($mailer, ['private_local', 'smtp'], true) || ($mailer === 'private_local' && ! app()->environment('local'))) {
            $invitation->update(['delivery_status' => 'not_configured']);

            return 'Account saved as pending. Invitation delivery is not configured; configure mail and resend.';
        }
        try {
            if ($this->emails->enabled()) {
                return 'Account saved as pending. The activation email was scheduled for delivery.';
            }

            $link = route('activation.show').'#'.$issued['token'];
            Mail::to($invitation->user->email)->send(new AccountInvitationMail($link));
            $invitation->update(['delivery_status' => 'local_preview']);

            return 'Account saved as pending. Invitation captured in the protected local mail inbox; no email was delivered externally.';
        } catch (Throwable) {
            $invitation->update(['delivery_status' => 'failed']);
            $this->audit->record('invitation.delivery_failed', $invitation);

            return 'Account saved as pending. Invitation delivery failed. Check mail configuration and resend.';
        }
    }

    public function setupState(#[\SensitiveParameter] string $token): string
    {
        if (! preg_match('/^[a-f0-9]{64}$/D', $token)) {
            return 'invalid';
        }

        $invitation = AccountInvitation::with('user.employee')->where('token_hash', hash('sha256', $token))->first();

        return $invitation && $invitation->user ? $this->state($invitation, $invitation->user) : 'invalid';
    }

    private function state(AccountInvitation $invitation, User $user): string
    {
        if ($invitation->revoked_at || $invitation->expires_at->isPast()) {
            return 'invalid';
        }
        if ($invitation->used_at && $user->account_status === 'active') {
            return 'activated';
        }
        if ($invitation->used_at || $user->account_status !== 'pending_activation' || $user->email_verified_at
            || ($user->employee && $user->employee->employment_status !== 'active')) {
            return 'invalid';
        }

        return 'ready';
    }

    public function activate(#[\SensitiveParameter] string $token, string $password): void
    {
        DB::transaction(function () use ($token, $password) {
            Role::whereKey(Role::HR)->lockForUpdate()->firstOrFail();
            $candidate = AccountInvitation::where('token_hash', hash('sha256', $token))->first();
            if (! $candidate) {
                throw ValidationException::withMessages(['token' => 'This invitation is invalid or expired.']);
            }
            $user = User::whereKey($candidate->user_id)->lockForUpdate()->firstOrFail();
            $invitation = AccountInvitation::whereKey($candidate->id)->lockForUpdate()->firstOrFail();
            if ($this->state($invitation, $user) !== 'ready') {
                throw ValidationException::withMessages(['token' => 'This invitation is invalid or expired.']);
            }
            $user->forceFill(['password' => $password, 'account_status' => 'active', 'email_verified_at' => now(), 'remember_token' => Str::random(60), 'session_version' => $user->session_version + 1])->save();
            $invitation->update(['used_at' => now()]);
            DB::table('password_reset_tokens')->where('email', $user->email)->delete();
            $this->audit->record('account.activated', $user, ['invitation_id' => $invitation->id]);
            if (app()->environment('local')) {
                LocalMailMessage::where('recipient_hash', hash('sha256', $user->email))->delete();
            }
        }, 3);
    }
}
