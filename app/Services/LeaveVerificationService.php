<?php

namespace App\Services;

use App\Models\LeaveApprovalDocument;
use App\Models\LeaveRequest;
use App\Models\SupervisorSignature;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

class LeaveVerificationService
{
    public function __construct(
        private LeaveApplicationDocumentService $documents,
        private WorkflowTransaction $transactions,
        private AuditService $audit,
        private WorkflowNotices $notices,
        private LeaveBalanceService $balances,
    ) {}

    public function canView(User $user, LeaveRequest $leave): bool
    {
        return $user->can('view', $leave) && (! $user->isSupervisor() || $user->can('review', $leave) || $leave->employee->user_id === $user->id);
    }

    public function inspect(LeaveApprovalDocument $document): array
    {
        try {
            $this->documents->bytes($document);
            $integrity = 'Match';
        } catch (HttpExceptionInterface $exception) {
            $integrity = $exception->getStatusCode() === 503 ? 'Mismatch' : 'Unavailable';
        } catch (Throwable) {
            $integrity = 'Unavailable';
        }

        $status = match (true) {
            $document->superseded_by_document_id !== null || $document->leave->approvalDocument?->id !== $document->id => 'superseded',
            $document->revoked_at !== null => 'revoked',
            $document->leave->status !== 'approved' || $document->leave->finalized_at === null || $document->generated_at === null => 'invalid',
            $integrity !== 'Match' => 'unavailable',
            default => 'valid',
        };

        return ['status' => $status, 'integrity' => $integrity];
    }

    public function revoke(User $actor, LeaveApprovalDocument $document, string $reason): void
    {
        $this->transactions->run($actor, function (User $fresh) use ($document, $reason): void {
            abort_unless($fresh->isHr(), 403);
            $leave = LeaveRequest::whereKey($document->leave_request_id)->lockForUpdate()->firstOrFail();
            $document = LeaveApprovalDocument::whereKey($document->id)->lockForUpdate()->firstOrFail();
            abort_unless($leave->status === 'approved' && $leave->approvalDocument?->id === $document->id && ! $document->revoked_at, 409);

            $revokedAt = now();
            $reversal = $this->balances->reverseApproval($leave, $fresh, $reason);
            $document->update(['revoked_at' => $revokedAt, 'revoked_by_user_id' => $fresh->id]);
            $leave->update(['status' => 'revoked']);
            $metadata = ['document_id' => $document->id, 'version' => $document->version, 'previous_status' => 'approved', 'status' => 'revoked', 'balance_transaction_id' => $reversal?->id];
            $leave->reviews()->create([
                'reviewer_user_id' => $fresh->id, 'action' => 'revoked', 'remarks' => $reason,
                'metadata' => $metadata, 'reviewed_at' => $revokedAt,
            ]);
            $this->audit->record('leave.document_revoked', $leave, $metadata);
            $this->notices->send($leave->employee->user, 'leave', 'HR revoked your approved leave request.', route('employee.leave.show', $leave), $leave->id);

            $supervisor = $leave->supervisor;
            if ($supervisor?->can('review', $leave)) {
                $this->notices->send($supervisor, 'leave', 'HR revoked an approved leave request assigned to you.', route('supervisor.leave.show', $leave), $leave->id);
            }

            $this->notices->hr('leave', 'An approved leave request was revoked by HR.', route('hr.leave.show', $leave), $leave->id);
        });
    }

    public function replace(User $actor, LeaveApprovalDocument $old, string $reason, int $signatureVersion): LeaveApprovalDocument
    {
        $path = 'leave-approval-documents/'.Str::uuid().'.bin';
        try {
            return $this->transactions->run($actor, function (User $fresh) use ($old, $reason, $signatureVersion, $path): LeaveApprovalDocument {
                $leave = LeaveRequest::whereKey($old->leave_request_id)->lockForUpdate()->firstOrFail();
                abort_unless($fresh->can('review', $leave), 403);
                $oldId = $old->id;
                $old = LeaveApprovalDocument::whereKey($oldId)->lockForUpdate()->firstOrFail();
                abort_unless($leave->approvalDocument?->id === $old->id && $leave->status === 'approved' && $leave->finalized_at !== null && $leave->form_snapshot !== null, 409);
                $signature = SupervisorSignature::activeFor($fresh)->first();
                if (! $signature || $signature->version !== $signatureVersion) {
                    throw ValidationException::withMessages(['signature_version' => 'Register a signature or reload to confirm the active signature version.']);
                }

                $replacement = $this->documents->generate($leave, $fresh, $signature, now(), $path, $old->version + 1);
                $old->update(['superseded_by_document_id' => $replacement->id]);
                $this->audit->record('leave.document_superseded', $leave, [
                    'document_id' => $old->id, 'version' => $old->version, 'replacement_id' => $replacement->id, 'reason' => $reason,
                    'signature_version' => $signature->version,
                ]);

                return $replacement;
            });
        } catch (Throwable $exception) {
            try {
                if (! LeaveApprovalDocument::where('storage_path', $path)->exists()) {
                    Storage::disk('leave')->delete($path);
                }
            } catch (Throwable $cleanupException) {
                report($cleanupException);
            }

            throw $exception;
        }
    }
}
