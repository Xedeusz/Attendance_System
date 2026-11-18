<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\EmployeeLeaveBalance;
use App\Models\LeaveApprovalDocument;
use App\Models\LeaveBalanceTransaction;
use App\Models\LeaveRequest;
use App\Models\LeaveReview;
use App\Models\LeaveSupportingDocument;
use App\Models\LeaveType;
use App\Models\Role;
use App\Models\SupervisorAssignment;
use App\Models\SupervisorSignature;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

class LeaveService
{
    public function __construct(private WorkflowTransaction $transactions, private AuditService $audit, private WorkflowNotices $notices, private LeaveApplicationDocumentService $formDocuments) {}

    private function fail(string $field, string $message): never
    {
        throw ValidationException::withMessages([$field => $message]);
    }

    private function employee(User $actor): Employee
    {
        $employee = $actor->employee;
        if (! $employee || $employee->employment_status !== 'active') {
            $this->fail('employee', 'An active employee profile is required. Contact HR.');
        }

        return $employee;
    }

    public function submit(User $actor, array $data, ?UploadedFile $file = null): LeaveRequest
    {
        $storedPath = null;
        try {
            return $this->transactions->run($actor, function (User $fresh) use ($data, $file, &$storedPath) {
                $employee = $this->employee($fresh);
                $type = LeaveType::whereKey($data['leave_type_id'])->where('is_active', true)->first();
                if (! $type) {
                    $this->fail('leave_type_id', 'Choose an active leave type.');
                }
                if (isset($data['leave_category']) && $data['leave_category'] !== $this->formDocuments->categoryFor($type)) {
                    $this->fail('leave_type_id', 'Choose a leave type that matches the selected box.');
                }
                $start = Carbon::parse($data['start_date']);
                $end = Carbon::parse($data['end_date']);
                $span = (int) $start->diffInDays($end) + 1;
                $halfDay = $data['half_day'] ?? null;
                $duration = (float) ($data['requested_duration'] ?? ($halfDay ? 0.5 : $span));
                if ($span > config('leave.max_request_calendar_days')) {
                    $this->fail('end_date', 'The date range can span a maximum of '.config('leave.max_request_calendar_days').' calendar days.');
                }
                if ($duration > $span) {
                    $this->fail('requested_duration', 'Requested days must fit the requested dates.');
                }
                $fraction = $duration !== floor($duration);
                if ($fraction && (! $type->allows_half_day || $span !== 1 || $duration !== 0.5 || ! $halfDay)) {
                    $this->fail('half_day', 'Half-day leave requires an enabled type, one date and AM or PM.');
                }
                if (! $fraction && $halfDay) {
                    $this->fail('half_day', 'AM/PM applies only to a single half-day request.');
                }
                if ($type->affects_balance && $start->year !== $end->year) {
                    $this->fail('end_date', 'Split balance-based requests across policy years.');
                }
                if (LeaveRequest::where('employee_id', $employee->id)->whereIn('status', [...LeaveRequest::PENDING, 'approved'])->whereDate('start_date', '<=', $data['end_date'])->whereDate('end_date', '>=', $data['start_date'])->exists()) {
                    $this->fail('start_date', 'These dates overlap a pending or approved leave application.');
                }
                if ($type->requires_document && ! $file) {
                    $this->fail('supporting_document', 'This leave type requires a supporting document.');
                }
                $formSnapshot = $this->formDocuments->snapshot($employee, $type, $data['other_leave_type'] ?? null);
                $leave = LeaveRequest::create(['employee_id' => $employee->id, 'leave_type_id' => $type->id, 'start_date' => $data['start_date'], 'end_date' => $data['end_date'], 'requested_duration' => $duration, 'half_day' => $halfDay, 'reason' => $data['reason'], 'type_snapshot' => $type->only(['name', 'requires_document', 'affects_balance', 'allows_half_day', 'duration_policy']), 'form_snapshot' => $formSnapshot, 'status' => 'awaiting_supervisor_assignment', 'submitted_at' => now()]);
                if ($file) {
                    $this->document($leave, $file, $storedPath);
                }
                $this->history($leave, 'submitted', null);
                $routed = $this->routeRequest($leave);
                if (! $routed) {
                    $this->notices->hr('leave', 'Leave application awaiting Supervisor assignment.', route('hr.leave.show', $leave), $leave->id);
                }
                $this->notices->send($fresh, 'leave', $routed ? 'Leave submitted for Supervisor approval.' : 'Your leave application is awaiting Supervisor assignment.', route('employee.leave.show', $leave), $leave->id);
                $this->audit->record('leave.submitted', $leave, ['employee_id' => $employee->id, 'status' => $leave->status, 'type_id' => $type->id]);

                return $leave;
            });
        } catch (Throwable $exception) {
            if ($storedPath) {
                Storage::disk('leave')->delete($storedPath);
            } throw $exception;
        }
    }

    private function document(LeaveRequest $leave, UploadedFile $file, ?string &$storedPath): string
    {
        $mime = $file->getMimeType();
        $bytes = file_get_contents($file->getRealPath());
        if (! in_array($mime, ['application/pdf', 'image/jpeg', 'image/png'], true) || strlen($bytes) > config('leave.document_max_kilobytes') * 1024) {
            $this->fail('supporting_document', 'Upload a permitted PDF, JPEG or PNG within the configured size limit.');
        }
        if ($mime === 'application/pdf' && ! str_starts_with($bytes, '%PDF-')) {
            $this->fail('supporting_document', 'The PDF content is invalid.');
        }
        if ($mime !== 'application/pdf') {
            $size = @getimagesizefromstring($bytes);
            if (! $size || $size[0] * $size[1] > 20000000) {
                $this->fail('supporting_document', 'The image content or dimensions are not supported.');
            }
            $decoded = @imagecreatefromstring($bytes);
            if ($decoded === false) {
                $this->fail('supporting_document', 'The image cannot be decoded. Upload a valid JPEG or PNG.');
            }
            imagedestroy($decoded);
        }
        $path = Str::uuid().'.bin';
        $storedPath = $path;
        Storage::disk('leave')->put($path, Crypt::encryptString($bytes));
        $name = mb_substr(preg_replace('/[^\pL\pN ._()-]/u', '_', basename($file->getClientOriginalName())), 0, 190);
        LeaveSupportingDocument::create(['leave_request_id' => $leave->id, 'storage_path' => $path, 'original_name' => $name, 'mime_type' => $mime, 'file_size' => strlen($bytes), 'purge_after' => config('leave.document_retention_days') === null ? null : now()->addDays(max(1, config('leave.document_retention_days')))]);

        return $path;
    }

    public function history(LeaveRequest $leave, string $action, ?string $remarks, array $metadata = []): void
    {
        LeaveReview::create(['leave_request_id' => $leave->id, 'reviewer_user_id' => auth()->id(), 'action' => $action, 'remarks' => $remarks, 'metadata' => $metadata, 'reviewed_at' => now()]);
    }

    public function routeRequest(LeaveRequest $leave): bool
    {
        if (! in_array($leave->status, LeaveRequest::PENDING, true)) {
            return false;
        }
        $next = SupervisorAssignment::current()->where('employee_id', $leave->employee_id)->where('supervisor_user_id', '!=', $leave->employee->user_id)->orderBy('id')->first()?->supervisor_user_id;
        $previous = $leave->assigned_supervisor_user_id;
        $status = $next ? 'pending_supervisor_approval' : 'awaiting_supervisor_assignment';
        if ($previous === $next && $leave->status === $status) {
            return false;
        }
        $leave->update(['assigned_supervisor_user_id' => $next, 'status' => $status]);
        $this->history($leave, $previous ? 'supervisor_reassigned' : 'supervisor_assigned', null, ['previous_supervisor_id' => $previous, 'supervisor_id' => $next]);
        $this->audit->record('leave.supervisor_changed', $leave, ['previous_supervisor_id' => $previous, 'supervisor_id' => $next]);
        if ($next) {
            $this->notices->send(User::findOrFail($next), 'leave', 'A leave application needs your final decision.', route('supervisor.leave.show', $leave), $leave->id);
        } else {
            $this->notices->hr('leave', 'Leave application needs an eligible Supervisor.', route('hr.leave.show', $leave), $leave->id);
        }
        if ($previous && $previous !== $next && User::find($previous)?->isSupervisor()) {
            $this->notices->send(User::findOrFail($previous), 'leave', 'A pending leave application has been reassigned.', route('supervisor.leave.index'), $leave->id);
        }

        return true;
    }

    public function refreshRouting(?int $employeeId = null): void
    {
        DB::transaction(function () use ($employeeId) {
            Role::whereKey(Role::HR)->lockForUpdate()->firstOrFail();
            foreach (LeaveRequest::with('employee')->whereIn('status', LeaveRequest::PENDING)->when($employeeId, fn ($q) => $q->where('employee_id', $employeeId))->lockForUpdate()->get() as $leave) {
                $this->routeRequest($leave);
            }
        }, 3);
    }

    public function decide(User $actor, LeaveRequest $target, array $data): LeaveRequest
    {
        $path = 'leave-approval-documents/'.Str::uuid().'.bin';
        try {
            return $this->transactions->run($actor, function (User $fresh) use ($target, $data, $path): LeaveRequest {
                $leave = LeaveRequest::whereKey($target->id)->lockForUpdate()->firstOrFail();
                $decisionAt = now();
                $metadata = ['previous_status' => $leave->status];
                Gate::forUser($fresh)->authorize('review', $leave);
                abort_unless($leave->status === 'pending_supervisor_approval', 409, 'This request already has a decision or requires assignment.');
                if ($data['decision'] === 'approved') {
                    $this->employee($leave->employee->user);
                    if (! $leave->employee->user->canAuthenticate()) {
                        $this->fail('decision', 'The requesting account is inactive. Contact HR.');
                    }
                    $type = $leave->type()->lockForUpdate()->firstOrFail();
                    if (! $type->is_active || (bool) $leave->type_snapshot['affects_balance'] !== $type->affects_balance || $leave->type_snapshot['duration_policy'] !== $type->duration_policy) {
                        $this->fail('decision', 'The leave policy changed or is inactive. Ask HR to review the configuration; submit a new request if needed.');
                    }
                    if ((float) $leave->requested_duration === 0.5 && ! $type->allows_half_day) {
                        $this->fail('decision', 'This type no longer permits half-day requests.');
                    }
                    if ($type->requires_document && ! $leave->documents()->whereNull('purged_at')->get()->contains(
                        fn (LeaveSupportingDocument $document): bool => Storage::disk('leave')->exists($document->storage_path)
                    )) {
                        $this->fail('decision', 'A required supporting document is unavailable.');
                    }
                    $charge = $type->affects_balance ? ($data['chargeable_days'] ?? null) : 0;
                    if ($charge === null) {
                        $this->fail('chargeable_days', 'Confirm chargeable days using the applicable work schedule/policy.');
                    }
                    $span = (int) $leave->start_date->diffInDays($leave->end_date) + 1;
                    if ((float) $charge > $span || (float) $charge > (float) $leave->requested_duration || (! $type->allows_half_day && (float) $charge !== floor((float) $charge))) {
                        $this->fail('chargeable_days', 'Chargeable days must not exceed the requested days and must follow the half-day policy.');
                    }
                    if ($type->affects_balance) {
                        $balance = EmployeeLeaveBalance::where('employee_id', $leave->employee_id)->where('leave_type_id', $type->id)->where('policy_period', $leave->start_date->format('Y'))->lockForUpdate()->first();
                        if (! $balance) {
                            $this->fail('chargeable_days', 'No balance is configured for this employee, leave type and policy year. HR must configure it first.');
                        }
                        $remaining = (int) round((float) $balance->available_days * 2) - (int) round((float) $charge * 2);
                        if ($remaining < 0) {
                            $this->fail('chargeable_days', 'Insufficient configured leave balance.');
                        }
                        $balance->update(['available_days' => number_format($remaining / 2, 2, '.', '')]);
                        LeaveBalanceTransaction::create(['employee_leave_balance_id' => $balance->id, 'leave_request_id' => $leave->id, 'authorized_by_user_id' => $fresh->id, 'transaction_type' => 'approved_debit', 'amount' => -(float) $charge, 'reason' => 'Final Supervisor approval']);
                    }
                    $signature = SupervisorSignature::activeFor($fresh)->first();
                    if (! $signature || $signature->version !== (int) ($data['signature_version'] ?? 0)) {
                        $this->fail('decision', 'Register an electronic signature or reload this application to confirm the current signature version.');
                    }
                    if (empty($data['confirmation'])) {
                        $this->fail('confirmation', 'Explicit confirmation is required to apply your signature.');
                    }
                    $leave->charged_duration = $charge;
                    $leave->form_snapshot ??= $this->formDocuments->legacySnapshot($leave);
                    $metadata['signature_version'] = $signature->version;
                    $decisionAt = now();
                    $document = $this->formDocuments->generate($leave, $fresh, $signature, $decisionAt, $path);
                    $metadata['document_reference'] = $document->document_reference;
                    $this->audit->record('leave.signing_confirmed', $leave, $metadata);
                    $this->audit->record('leave.official_pdf_generated', $leave, $metadata);
                }
                $leave->fill(['status' => $data['decision'], 'reviewed_at' => $decisionAt, 'finalized_at' => $decisionAt])->save();
                $metadata += ['status' => $leave->status, 'charged_duration' => $leave->charged_duration];
                $this->history($leave, $data['decision'], $data['remarks'] ?? null, $metadata);
                $this->audit->record('leave.'.$data['decision'], $leave, $metadata + ['employee_id' => $leave->employee_id]);
                $message = $data['decision'] === 'approved'
                    ? 'Your leave application was approved and signed. Your official form is available.'
                    : 'Your leave application was rejected.';
                $this->notices->send($leave->employee->user, 'leave', $message, route('employee.leave.show', $leave), $leave->id);

                return $leave;
            });
        } catch (Throwable $exception) {
            if ($data['decision'] === 'approved') {
                try {
                    if (! LeaveApprovalDocument::where('storage_path', $path)->exists()) {
                        Storage::disk('leave')->delete($path);
                    }
                } catch (Throwable $cleanupException) {
                    report($cleanupException);
                }
            }

            throw $exception;
        }
    }

    public function cancel(User $actor, LeaveRequest $target, string $reason): void
    {
        $this->transactions->run($actor, function (User $fresh) use ($target, $reason) {
            $leave = LeaveRequest::whereKey($target->id)->lockForUpdate()->firstOrFail();
            Gate::forUser($fresh)->authorize('cancel', $leave);
            abort_unless(in_array($leave->status, LeaveRequest::PENDING, true), 409, 'Only pending leave may be cancelled.');
            $previous = $leave->status;
            $leave->update(['status' => 'cancelled', 'cancelled_at' => now(), 'finalized_at' => now()]);
            $this->history($leave, 'cancelled', $reason, ['previous_status' => $previous]);
            $this->audit->record('leave.cancelled', $leave, ['previous_status' => $previous]);
            if ($leave->supervisor && Gate::forUser($leave->supervisor)->allows('review', $leave)) {
                $this->notices->send($leave->supervisor, 'leave', 'An assigned leave application was cancelled.', route('supervisor.leave.show', $leave), $leave->id);
            }
            $this->notices->send($fresh, 'leave', 'Your pending leave application was cancelled.', route('employee.leave.show', $leave), $leave->id);
        });
    }

    public function adjustBalance(User $actor, array $data): EmployeeLeaveBalance
    {
        return $this->transactions->run($actor, function (User $fresh) use ($data) {
            abort_unless($fresh->isHr(), 403);
            $balance = EmployeeLeaveBalance::firstOrCreate(['employee_id' => $data['employee_id'], 'leave_type_id' => $data['leave_type_id'], 'policy_period' => $data['policy_period']], ['available_days' => 0]);
            $balance = EmployeeLeaveBalance::whereKey($balance->id)->lockForUpdate()->firstOrFail();
            $units = (int) round((float) $balance->available_days * 2) + (int) round((float) $data['amount'] * 2);
            if ($units < 0) {
                $this->fail('amount', 'An adjustment cannot make the available balance negative.');
            }
            $balance->update(['available_days' => number_format($units / 2, 2, '.', '')]);
            LeaveBalanceTransaction::create(['employee_leave_balance_id' => $balance->id, 'authorized_by_user_id' => $fresh->id, 'transaction_type' => 'hr_adjustment', 'amount' => $data['amount'], 'reason' => $data['reason']]);
            $this->audit->record('leave.balance_adjusted', $balance, ['amount' => $data['amount'], 'period' => $data['policy_period']]);

            return $balance;
        });
    }
}
