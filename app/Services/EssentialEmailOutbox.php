<?php

namespace App\Services;

use App\Jobs\SendEssentialEmail;
use App\Mail\EssentialEmailFailure;
use App\Models\AccountInvitation;
use App\Models\EssentialEmail;
use App\Models\LeaveApprovalDocument;
use App\Models\LeaveRequest;
use App\Models\LeaveReview;
use App\Models\User;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Throwable;

class EssentialEmailOutbox
{
    public function enabled(): bool
    {
        return config('mail.default') === 'smtp';
    }

    public function accountSetup(AccountInvitation $invitation, #[\SensitiveParameter] string $token): ?EssentialEmail
    {
        return $this->record(EssentialEmail::ACCOUNT_SETUP, (string) $invitation->id, $invitation->user, [
            'account_invitation_id' => $invitation->id, 'auth_token' => $token, 'expires_at' => $invitation->expires_at,
        ]);
    }

    public function reset(User $owner, #[\SensitiveParameter] string $token): void
    {
        try {
            $this->record(EssentialEmail::PASSWORD_RESET, hash('sha256', $token), $owner, [
                'auth_token' => $token, 'expires_at' => now()->addMinutes(config('auth.passwords.'.(config('fortify.passwords') ?: config('auth.defaults.passwords')).'.expire')),
            ]);
        } catch (Throwable) {
            // Keep Fortify's generic response even if the outbox is temporarily unavailable.
            Log::error('Password reset email could not be recorded.', ['recipient_user_id' => $owner->id]);
        }
    }

    public function submitted(LeaveRequest $leave): void
    {
        if ($this->enabled() && $leave->supervisor && Gate::forUser($leave->supervisor)->allows('review', $leave)) {
            $this->record(EssentialEmail::LEAVE_SUBMITTED, (string) $leave->id, $leave->supervisor, ['leave_request_id' => $leave->id]);
        }
    }

    public function decision(LeaveRequest $leave, LeaveReview $review, ?LeaveApprovalDocument $document): void
    {
        if (! $this->enabled()) {
            return;
        }

        $type = $review->action === 'approved' ? EssentialEmail::LEAVE_APPROVED : EssentialEmail::LEAVE_REJECTED;
        $this->record($type, (string) $leave->id, $leave->employee->user, [
            'leave_request_id' => $leave->id, 'leave_review_id' => $review->id,
            'leave_approval_document_id' => $document?->id, 'document_reference' => $document?->document_reference,
        ]);
    }

    private function record(string $type, string $key, User $recipient, #[\SensitiveParameter] array $attributes): ?EssentialEmail
    {
        if (! $this->enabled()) {
            return null;
        }

        $email = EssentialEmail::firstOrCreate(['event_key' => $type.':'.$key], $attributes + [
            'type' => $type, 'recipient_user_id' => $recipient->id, 'recipient_email' => $recipient->email,
            'link_base' => rtrim(config('mail.essential.link_base') ?: config('app.url'), '/'),
        ]);
        if ($email->wasRecentlyCreated) {
            DB::afterCommit(fn () => $this->enqueue($email->id));
        }

        return $email;
    }

    public function enqueue(int $id): void
    {
        try {
            $email = EssentialEmail::whereKey($id)->where('status', 'queued')->whereNull('enqueued_at')->first();
            if (! $email || ! $this->enabled()) {
                return;
            }
            if ($email->type === EssentialEmail::LEAVE_APPROVED) {
                app(EssentialEmailDelivery::class)->prepare($email);
            }
            // A crash between push and this marker can queue the same ID twice. The job claims it atomically.
            Bus::dispatch(new SendEssentialEmail($id));
            EssentialEmail::whereKey($id)->where('status', 'queued')->update(['enqueued_at' => now(), 'failure_reason' => null]);
        } catch (EssentialEmailFailure $failure) {
            $this->finish($email, $failure->outcome, $failure->getMessage());
        } catch (Throwable) {
            Log::error('Essential email could not be enqueued.', ['email_id' => $id]);
        }
    }

    public function finish(EssentialEmail $email, string $status, ?string $reason = null): void
    {
        $keepToken = in_array($status, ['retrying', 'failed'], true) && $email->expires_at?->isFuture();
        $email->forceFill([
            'status' => $status,
            'failure_reason' => $reason,
            'processed_at' => $status === 'retrying' ? null : now(),
            'attachment_included' => $status === 'submitted' && $email->type === EssentialEmail::LEAVE_APPROVED,
            ...($keepToken ? [] : ['auth_token' => null]),
        ])->save();

        if ($email->account_invitation_id && in_array($status, ['submitted', 'failed', 'uncertain'], true)) {
            AccountInvitation::whereKey($email->account_invitation_id)->whereNull('used_at')->whereNull('revoked_at')
                ->update(['delivery_status' => $status === 'submitted' ? 'submitted' : 'failed']);
        }
    }
}
