<?php

namespace App\Services;

use App\Models\AttendanceCorrectionRequest;
use App\Models\AttendanceRecord;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class AttendanceCorrectionService
{
    public function __construct(private WorkflowTransaction $transactions, private AuditService $audit, private WorkflowNotices $notices, private AttendanceComputationService $computations, private AttendancePolicyResolver $policies) {}

    private function fail(string $field, string $message): never
    {
        throw ValidationException::withMessages([$field => $message]);
    }

    private function timestamp(?string $value): ?Carbon
    {
        return $value ? Carbon::createFromFormat('!Y-m-d\TH:i', $value, config('attendance.timezone'))->utc() : null;
    }

    public function submit(User $actor, array $data): AttendanceCorrectionRequest
    {
        return $this->transactions->run($actor, function (User $fresh) use ($data) {
            abort_if($fresh->isSupervisor(), 403);
            $employee = $fresh->employee;
            if (! $employee || $employee->employment_status !== 'active') {
                $this->fail('employee', 'An active employee profile is required.');
            }
            $record = empty($data['attendance_record_id']) ? null : AttendanceRecord::where('employee_id', $employee->id)->findOrFail($data['attendance_record_id']);
            if ($record && $record->review_status === 'pending') {
                $this->fail('attendance_record_id', 'Resolve the original evidence review before requesting a correction.');
            }
            if (! $record && $data['correction_type'] !== 'missing_record') {
                $this->fail('attendance_record_id', 'Choose your attendance record, or use Missing record.');
            }
            if ($record && $data['correction_type'] === 'missing_record') {
                $this->fail('correction_type', 'A missing-record request must not reference an existing record.');
            }
            $in = $this->timestamp($data['requested_time_in_at'] ?? null);
            $out = $this->timestamp($data['requested_time_out_at'] ?? null);
            if (! $in && ! $out) {
                $this->fail('requested_time_in_at', 'Provide at least one proposed timestamp.');
            }
            if (in_array($data['correction_type'], ['forgotten_time_in', 'incorrect_time_in', 'missing_record'], true) && ! $in) {
                $this->fail('requested_time_in_at', 'A proposed Time In is required.');
            }
            if (in_array($data['correction_type'], ['forgotten_time_out', 'incorrect_time_out'], true) && ! $out) {
                $this->fail('requested_time_out_at', 'A proposed Time Out is required.');
            }
            foreach ([$in, $out] as $stamp) {
                if ($stamp && $stamp->gt(now())) {
                    $this->fail('requested_time_in_at', 'A correction cannot create a future attendance time.');
                }
            }
            if ($in && $in->copy()->timezone(config('attendance.timezone'))->toDateString() !== $data['requested_date']) {
                $this->fail('requested_date', 'Requested date must match the proposed Time In in the business timezone.');
            }
            if ($record && ! $in && $record->work_date->toDateString() !== $data['requested_date']) {
                $this->fail('requested_date', 'Choose the original attendance work date when Time In is unchanged.');
            }
            $effectiveIn = $in ?? $record?->effective_time_in_at ?? $record?->time_in_at;
            $effectiveOut = $out ?? $record?->effective_time_out_at ?? $record?->time_out_at;
            if ((! $record || $record->session_status === 'cancelled') && (! $effectiveIn || ! $effectiveOut)) {
                $this->fail('requested_time_out_at', 'A supplemental manual entry needs both Time In and Time Out.');
            }
            if ($effectiveIn && $effectiveOut && $effectiveOut->lte($effectiveIn)) {
                $this->fail('requested_time_out_at', 'Time Out must be after Time In.');
            }
            $key = hash('sha256', $employee->id.':'.($record?->id ?? $data['requested_date']));
            if (AttendanceCorrectionRequest::where('pending_key', $key)->exists()) {
                $this->fail('attendance_record_id', 'A correction for this record/date is already pending.');
            }
            if (! $record && AttendanceCorrectionRequest::where('employee_id', $employee->id)->whereNull('attendance_record_id')->whereDate('requested_date', $data['requested_date'])->where('status', 'approved')->exists()) {
                $this->fail('requested_date', 'An approved manual entry already exists for this date. Contact HR; duplicate entries are not permitted.');
            }
            $correction = AttendanceCorrectionRequest::create(['employee_id' => $employee->id, 'attendance_record_id' => $record?->id, 'branch_id' => $record?->branch_id ?? $this->policies->branchFor($employee, $data['requested_date'])?->id, 'correction_type' => $data['correction_type'], 'requested_date' => $data['requested_date'], 'requested_time_in_at' => $effectiveIn, 'requested_time_out_at' => $effectiveOut, 'original_values' => $record?->only(['time_in_at', 'time_out_at', 'effective_time_in_at', 'effective_time_out_at', 'session_status', 'verification_status', 'review_status']) ?? [], 'record_revision' => $record?->correction_version ?? 0, 'reason' => $data['reason'], 'status' => 'pending', 'pending_key' => $key, 'submitted_at' => now()]);
            $this->audit->record('attendance.correction_submitted', $correction, ['employee_id' => $employee->id, 'attendance_record_id' => $record?->id]);
            $this->notices->hr('correction', 'An attendance correction needs HR review.', route('hr.corrections.show', $correction), $correction->id);
            $this->notices->send($fresh, 'correction', 'Attendance correction submitted for HR review.', route('employee.corrections.show', $correction), $correction->id);

            return $correction;
        });
    }

    public function decide(User $actor, AttendanceCorrectionRequest $target, array $data): void
    {
        $this->transactions->run($actor, function (User $fresh) use ($target, $data) {
            $correction = AttendanceCorrectionRequest::whereKey($target->id)->lockForUpdate()->firstOrFail();
            Gate::forUser($fresh)->authorize('review', $correction);
            abort_unless($correction->status === 'pending', 409, 'This correction is no longer pending.');
            if ($data['decision'] === 'approved') {
                $record = $correction->record()->lockForUpdate()->first();
                $currentOriginal = $record ? json_decode(json_encode($record->only(array_keys($correction->original_values)), JSON_THROW_ON_ERROR), true, 512, JSON_THROW_ON_ERROR) : null;
                if ($record && ($record->correction_version !== $correction->record_revision || $record->review_status === 'pending' || $currentOriginal !== $correction->original_values)) {
                    abort(409, 'The original record changed. Submit a new correction based on its current state.');
                }
                $in = $correction->requested_time_in_at;
                $out = $correction->requested_time_out_at;
                if ((! $record || $record->session_status === 'cancelled') && (! $in || ! $out)) {
                    $this->fail('decision', 'A supplemental manual entry requires a complete Time In and Time Out interval.');
                }
                if (! $in || ($out && $out->lte($in))) {
                    $this->fail('decision', 'A valid effective Time In and chronological Time Out are required.');
                }
                $end = $out ?? now();
                $overlap = AttendanceRecord::where('employee_id', $correction->employee_id)->when($record, fn ($q) => $q->whereKeyNot($record->id))->whereIn('session_status', ['open', 'completed'])->whereRaw('COALESCE(effective_time_in_at,time_in_at) < ?', [$end])->where(fn ($q) => $q->whereRaw('COALESCE(effective_time_out_at,time_out_at) > ?', [$in])->orWhereRaw('COALESCE(effective_time_out_at,time_out_at) IS NULL'))->exists();
                $manualOverlap = AttendanceCorrectionRequest::where('employee_id', $correction->employee_id)->whereNull('attendance_record_id')->where('status', 'approved')->whereKeyNot($correction->id)->where('requested_time_in_at', '<', $end)->where(fn ($q) => $q->whereNull('requested_time_out_at')->orWhere('requested_time_out_at', '>', $in))->exists();
                if ($overlap || $manualOverlap) {
                    $this->fail('decision', 'The proposed effective attendance overlaps another recorded or manually corrected session.');
                }
                if ($record) {
                    $this->computations->capture($record, 'correction_baseline');
                    $record->forceFill(['effective_time_in_at' => $in, 'effective_time_out_at' => $out, 'correction_version' => $record->correction_version + 1])->save();
                    $this->computations->capture($record, 'correction');
                } else {
                    $this->computations->captureManual($correction);
                }
            }
            $correction->update(['status' => $data['decision'], 'pending_key' => null, 'reviewed_by_user_id' => $fresh->id, 'reviewed_at' => now(), 'review_remarks' => $data['remarks']]);
            $this->audit->record('attendance.correction_'.$data['decision'], $correction, ['attendance_record_id' => $correction->attendance_record_id, 'effective_time_in_at' => $correction->requested_time_in_at?->toIso8601String(), 'effective_time_out_at' => $correction->requested_time_out_at?->toIso8601String()]);
            $this->notices->send($correction->employee->user, 'correction', 'Your attendance correction was '.$data['decision'].'.', route('employee.corrections.show', $correction), $correction->id);
        });
    }

    public function cancel(User $actor, AttendanceCorrectionRequest $target): void
    {
        $this->transactions->run($actor, function (User $fresh) use ($target) {
            $correction = AttendanceCorrectionRequest::whereKey($target->id)->lockForUpdate()->firstOrFail();
            abort_unless(! $fresh->isSupervisor() && $correction->employee->user_id === $fresh->id, 403);
            abort_unless($correction->status === 'pending', 409);
            $correction->update(['status' => 'cancelled', 'pending_key' => null]);
            $this->audit->record('attendance.correction_cancelled', $correction);
        });
    }
}
