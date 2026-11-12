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
    public function __construct(private AuditService $audit) {}

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

        return compact('invitation', 'token');
    }

    public function deliver(array $issued): string
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
            $link = route('activation.show').'#'.$issued['token'];
            Mail::to($invitation->user->email)->send(new AccountInvitationMail($link));
            $local = $mailer === 'private_local';
            $invitation->update(['delivery_status' => $local ? 'local_preview' : 'submitted']);

            return $local ? 'Account saved as pending. Invitation captured in the protected local mail inbox; no email was delivered externally.' : 'Account saved as pending. Invitation submitted to the configured SMTP server; inbox delivery is not guaranteed.';
        } catch (Throwable) {
            $invitation->update(['delivery_status' => 'failed']);
            $this->audit->record('invitation.delivery_failed', $invitation);

            return 'Account saved as pending. Invitation delivery failed. Check mail configuration and resend.';
        }
    }

    public function activate(string $token, string $password): void
    {
        DB::transaction(function () use ($token, $password) {
            Role::whereKey(Role::HR)->lockForUpdate()->firstOrFail();
            $candidate = AccountInvitation::where('token_hash', hash('sha256', $token))->first();
            if (! $candidate) {
                throw ValidationException::withMessages(['token' => 'This invitation is invalid. Ask HR for a new invitation.']);
            }
            $user = User::whereKey($candidate->user_id)->lockForUpdate()->firstOrFail();
            $invitation = AccountInvitation::whereKey($candidate->id)->lockForUpdate()->firstOrFail();
            $reason = match (true) {
                (bool) $invitation->used_at => 'This invitation has already been used.',
                (bool) $invitation->revoked_at => 'This invitation was revoked or replaced. Ask HR for the latest invitation.',
                $invitation->expires_at->isPast() => 'This invitation has expired. Ask HR to resend it.',
                $user->account_status === 'deactivated' => 'This account is deactivated. Contact HR.',
                $user->account_status !== 'pending_activation' => 'This account is not awaiting activation.',
                $user->employee && $user->employee->employment_status !== 'active' => 'This employee record is inactive. Contact HR.',
                default => null,
            };
            if ($reason) {
                throw ValidationException::withMessages(['token' => $reason]);
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
