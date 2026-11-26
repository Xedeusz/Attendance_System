<?php

namespace App\Services;

use App\Mail\AccountInvitationMail;
use App\Mail\EssentialEmailFailure;
use App\Mail\EssentialNotificationMail;
use App\Models\EssentialEmail;
use App\Models\LeaveRequest;
use App\Models\User;
use App\Support\LeaveDays;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Throwable;

class EssentialEmailDelivery
{
    public function __construct(private LeaveApplicationDocumentService $documents) {}

    public function send(EssentialEmail $email): void
    {
        if (config('mail.default') !== 'smtp') {
            throw new EssentialEmailFailure('smtp_disabled');
        }

        $smtp = config('mail.mailers.smtp');
        if ($smtp['transport'] === 'smtp' && strtolower($smtp['host']) === 'smtp.gmail.com'
            && (! $smtp['require_tls'] || ! $smtp['username'] || ! preg_match('/^[a-zA-Z0-9]{16}$/D', (string) $smtp['password']))) {
            throw new EssentialEmailFailure('gmail_app_password_or_tls_not_configured');
        }

        $mailable = $this->prepare($email);
        $limit = max(1, (int) config('mail.essential.daily_limit'));
        $key = 'essential-smtp:'.hash('sha256', (string) config('mail.mailers.smtp.username'));

        try {
            $sent = DB::transaction(function () use ($email, $mailable, $key, $limit) {
                $this->confirmDeliveryState($email);
                if (RateLimiter::hit($key, 86400) > $limit) {
                    throw new EssentialEmailFailure('application_smtp_quota', retryable: true);
                }

                return Mail::mailer('smtp')->to($email->recipient_email)->send($mailable);
            });
        } catch (TransportExceptionInterface $exception) {
            $code = (int) $exception->getCode();

            throw match (true) {
                in_array($code, [530, 534, 535], true) => new EssentialEmailFailure('smtp_authentication_failed'),
                $code >= 400 && $code < 500 => new EssentialEmailFailure('smtp_temporary_rejection', retryable: true),
                $code === 550 && str_contains($exception->getMessage(), '5.4.5') => new EssentialEmailFailure('smtp_quota_exceeded', retryable: true),
                $code >= 500 && $code < 600 => new EssentialEmailFailure('smtp_permanent_rejection'),
                default => new EssentialEmailFailure('submission_outcome_unknown', outcome: 'uncertain'),
            };
        }

        if (! $sent) {
            throw new EssentialEmailFailure('no_smtp_submission_result', outcome: 'uncertain');
        }
    }

    private function confirmDeliveryState(EssentialEmail $email): void
    {
        $recipient = $email->recipient()->lockForUpdate()->first();
        if (! $recipient || ! hash_equals($email->recipient_email, $recipient->email)) {
            throw new EssentialEmailFailure('recipient_changed', outcome: 'skipped');
        }

        if ($email->type === EssentialEmail::ACCOUNT_SETUP) {
            $invitation = $email->invitation()->first();
            if ($recipient->account_status !== 'pending_activation' || ! $invitation || $invitation->revoked_at || $invitation->used_at || $invitation->expires_at->isPast()) {
                throw new EssentialEmailFailure('invitation_no_longer_valid', outcome: 'skipped');
            }

            return;
        }
        if (! $recipient->canAuthenticate()) {
            throw new EssentialEmailFailure('recipient_inactive', outcome: 'skipped');
        }
        if ($email->type === EssentialEmail::PASSWORD_RESET) {
            if (! Password::broker(config('fortify.passwords'))->tokenExists($recipient, $email->auth_token)) {
                throw new EssentialEmailFailure('reset_token_no_longer_valid', outcome: 'skipped');
            }

            return;
        }

        // Replacement and revocation acquire this same leave lock before changing the official document.
        $leave = $email->leave()->lockForUpdate()->first();
        if (! $leave || ($email->type !== EssentialEmail::LEAVE_SUBMITTED && $leave->employee->user_id !== $recipient->id)) {
            throw new EssentialEmailFailure('leave_or_owner_changed', outcome: 'skipped');
        }
        if ($email->type === EssentialEmail::LEAVE_SUBMITTED && ($leave->status !== 'pending_supervisor_approval'
            || $leave->assigned_supervisor_user_id !== $recipient->id || ! Gate::forUser($recipient)->allows('review', $leave))) {
            throw new EssentialEmailFailure('supervisor_no_longer_authorized', outcome: 'skipped');
        }
        if ($email->type === EssentialEmail::LEAVE_APPROVED) {
            $latest = $leave->approvalDocument;
            if ($leave->status !== 'approved' || ! $latest || $latest->revoked_at) {
                throw new EssentialEmailFailure('approval_no_longer_current', outcome: 'skipped');
            }
            if ($latest->id !== $email->leave_approval_document_id || $latest->superseded_by_document_id) {
                throw new EssentialEmailFailure('approval_document_changed', retryable: true);
            }
        }
    }

    public function prepare(EssentialEmail $email): EssentialNotificationMail
    {
        $recipient = $email->recipient;
        if (! $recipient || ! hash_equals($email->recipient_email, $recipient->email) || ($email->expires_at && $email->expires_at->isPast())) {
            throw new EssentialEmailFailure('recipient_changed_or_token_expired', outcome: 'skipped');
        }

        if ($email->type === EssentialEmail::ACCOUNT_SETUP) {
            $invitation = $email->invitation;
            if (! $invitation || $invitation->user_id !== $recipient->id || $recipient->account_status !== 'pending_activation'
                || ($recipient->employee && $recipient->employee->employment_status !== 'active')
                || $invitation->used_at || $invitation->revoked_at || $invitation->expires_at->isPast()
                || ! $email->auth_token || ! hash_equals($invitation->token_hash, hash('sha256', $email->auth_token))) {
                throw new EssentialEmailFailure('invitation_no_longer_valid', outcome: 'skipped');
            }

            return new AccountInvitationMail(
                $this->link('activation.show').'#'.$email->auth_token,
                $recipient,
            );
        }

        if (! $recipient->canAuthenticate()) {
            throw new EssentialEmailFailure('recipient_inactive', outcome: 'skipped');
        }
        if ($email->type === EssentialEmail::PASSWORD_RESET) {
            if (! $email->auth_token || ! Password::broker(config('fortify.passwords'))->tokenExists($recipient, $email->auth_token)) {
                throw new EssentialEmailFailure('reset_token_no_longer_valid', outcome: 'skipped');
            }

            return new EssentialNotificationMail(
                subject: 'Your LIMCOMA password reset request',
                name: $recipient->name,
                message: 'We received a password reset request for your LIMCOMA account.',
                action: 'Reset password',
                url: $this->link('password.reset', ['token' => $email->auth_token, 'email' => $recipient->email]),
                note: 'This link expires '.config('auth.passwords.'.(config('fortify.passwords') ?: config('auth.defaults.passwords')).'.expire').' minutes after the request. If you did not request a reset, you can safely ignore this email.',
            );
        }

        return $this->leaveMail($email, $recipient);
    }

    private function leaveMail(EssentialEmail $email, User $recipient): EssentialNotificationMail
    {
        $leave = $email->leave;
        if (! $leave) {
            throw new EssentialEmailFailure('leave_unavailable', outcome: 'skipped');
        }

        $fields = $this->leaveFields($leave);
        if ($email->type === EssentialEmail::LEAVE_SUBMITTED) {
            if ($leave->status !== 'pending_supervisor_approval' || $leave->assigned_supervisor_user_id !== $recipient->id
                || ! Gate::forUser($recipient)->allows('review', $leave)) {
                throw new EssentialEmailFailure('supervisor_no_longer_authorized', outcome: 'skipped');
            }

            return new EssentialNotificationMail(
                subject: 'LIMCOMA leave application awaiting your review',
                name: $recipient->name,
                message: 'An employee assigned to you submitted a leave application for your final decision.',
                action: 'Review leave application',
                url: $this->link('supervisor.leave.show', [$leave]),
                fields: $fields + ['Days requested' => LeaveDays::format($leave->requested_duration), 'Submitted' => $this->timestamp($leave->submitted_at), 'Status' => 'Pending Supervisor approval'],
            );
        }

        $status = match ($email->type) {
            EssentialEmail::LEAVE_APPROVED => 'approved',
            EssentialEmail::LEAVE_REJECTED => 'rejected',
            default => throw new EssentialEmailFailure('unknown_email_type'),
        };
        $review = $email->review;
        if ($leave->employee->user_id !== $recipient->id || $leave->status !== $status
            || ! $review || $review->leave_request_id !== $leave->id || $review->action !== $status) {
            throw new EssentialEmailFailure('leave_decision_or_owner_changed', outcome: 'skipped');
        }

        $url = $this->link('employee.leave.show', [$leave]);
        if ($status === 'rejected') {
            return new EssentialNotificationMail(
                subject: 'LIMCOMA leave application rejected',
                name: $recipient->name,
                message: 'Your Supervisor has rejected your leave application.',
                action: 'View leave application',
                url: $url,
                fields: $fields + ['Rejected by' => $review->reviewer?->name ?? 'Supervisor', 'Rejection date' => $this->timestamp($review->reviewed_at), 'Status' => 'Rejected', 'Remarks' => $review->remarks],
            );
        }

        $original = $email->leave_approval_document_id;
        $document = $leave->approvalDocument;
        if (! $original || ! $document || $document->leave_request_id !== $leave->id
            || ! $leave->finalized_at || $document->revoked_at || $document->superseded_by_document_id
            || ! $review->reviewer_user_id || $document->approved_by_user_id !== $review->reviewer_user_id
            || $document->signature?->supervisor_user_id !== $review->reviewer_user_id
            || ! $leave->documentsOfApproval()->whereKey($original)->where('approved_by_user_id', $review->reviewer_user_id)->exists()) {
            throw new EssentialEmailFailure('signed_approval_not_verified');
        }

        try {
            $pdf = $this->documents->bytes($document);
        } catch (HttpExceptionInterface $exception) {
            throw $exception->getStatusCode() === 404
                ? new EssentialEmailFailure('signed_pdf_unavailable', retryable: true)
                : new EssentialEmailFailure('signed_pdf_integrity_failed');
        } catch (Throwable) {
            throw new EssentialEmailFailure('signed_pdf_could_not_be_read', retryable: true);
        }

        if (! str_starts_with($pdf, '%PDF-') || ! str_ends_with(rtrim($pdf), '%%EOF')) {
            throw new EssentialEmailFailure('signed_pdf_invalid_format');
        }
        $encoded = 4 * (int) ceil(strlen($pdf) / 3);
        $messageBytes = $encoded + 2 * (int) ceil($encoded / 76) + 65536;
        if ($messageBytes > (int) config('mail.essential.max_message_bytes')) {
            throw new EssentialEmailFailure('signed_pdf_above_message_limit');
        }

        if ($document->id !== $original) {
            $email->forceFill(['leave_approval_document_id' => $document->id, 'document_reference' => $document->document_reference])->save();
        }
        $fileReference = preg_replace('/[^A-Za-z0-9_-]/', '_', $document->document_reference);

        return new EssentialNotificationMail(
            subject: 'LIMCOMA leave application approved',
            name: $recipient->name,
            message: 'Your leave application has been approved by your Supervisor. Your official approved Leave Application Form with the Supervisor’s electronic signature is attached as a PDF.',
            action: 'View leave application',
            url: $url,
            fields: $fields + ['Approved leave days' => LeaveDays::format($leave->requested_duration), 'Approved by' => $document->approver_name, 'Approval date' => $this->timestamp($leave->finalized_at), 'Status' => 'Approved', 'Official PDF reference' => $document->document_reference],
            note: 'Please keep the attached PDF for your records.',
            pdf: $pdf,
            filename: 'LIMCOMA_Approved_Leave_'.$fileReference.'.pdf',
        );
    }

    private function leaveFields(LeaveRequest $leave): array
    {
        return [
            'Employee' => $leave->form_snapshot['name'] ?? $leave->employee->full_name,
            'Application reference' => '#'.$leave->id,
            'Leave type' => $leave->type_snapshot['name'],
            'Leave dates' => $leave->start_date->format('M j, Y').' to '.$leave->end_date->format('M j, Y'),
        ];
    }

    private function timestamp(CarbonInterface $date): string
    {
        return $date->copy()->timezone(config('attendance.timezone'))->format('M j, Y g:i A T');
    }

    private function link(string $route, #[\SensitiveParameter] array $parameters = []): string
    {
        $origin = rtrim(config('mail.essential.link_base') ?: config('app.url'), '/');
        $base = parse_url($origin);
        $host = strtolower(rtrim(trim($base['host'] ?? '', '[]'), '.'));
        if (($base['scheme'] ?? null) !== 'https' || ! str_contains($host, '.') || str_ends_with($host, '.localhost')
            || filter_var($host, FILTER_VALIDATE_IP) || ! filter_var($host, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME)
            || (app()->isProduction() && str_ends_with($host, '.trycloudflare.com'))
            || isset($base['user']) || isset($base['query']) || isset($base['fragment'])) {
            throw new EssentialEmailFailure('invalid_application_link_base');
        }

        return $origin.route($route, $parameters, absolute: false);
    }
}
