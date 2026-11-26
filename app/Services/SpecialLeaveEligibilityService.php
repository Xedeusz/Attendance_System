<?php

namespace App\Services;

use App\Models\LeavePolicy;
use App\Models\LeaveType;
use App\Models\SpecialLeaveEligibilityActivity;
use App\Models\SpecialLeaveEligibilityDocument;
use App\Models\SpecialLeaveEligibilityRequest;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class SpecialLeaveEligibilityService
{
    public function __construct(private LeaveBalanceService $balances, private AuditService $audit, private WorkflowNotices $notices, private LeaveTypeVisibilityService $visibility) {}

    public function submit(User $actor, array $data, array $documents): SpecialLeaveEligibilityRequest
    {
        $employee = $actor->employee;
        abort_unless($employee && $employee->employment_status === 'active', 403);
        $type = LeaveType::query()->whereKey($data['leave_type_id'])->whereIn('code', ['MATERNITY', 'PATERNITY'])->where('is_active', true)->firstOrFail();
        if (! $this->visibility->canInitiateVerification($employee, $type)) {
            throw ValidationException::withMessages([
                'leave_type_id' => 'This leave type is not available for your current eligibility record. Please contact HR for verification.',
            ]);
        }
        $categoryIsValid = $type->code === 'PATERNITY'
            ? in_array($data['event_category'], ['paternity_qualifying_event', 'other_for_hr_review'], true)
            : $data['event_category'] !== 'paternity_qualifying_event';
        if (! $categoryIsValid) {
            throw ValidationException::withMessages(['event_category' => 'Choose an event category that matches the selected special leave type.']);
        }
        $eventDate = $data['actual_event_date'] ?? $data['expected_event_date'];
        $duplicate = SpecialLeaveEligibilityRequest::query()->whereBelongsTo($employee)->whereBelongsTo($type, 'type')
            ->where(fn ($query) => $query->whereDate('actual_event_date', $eventDate)->orWhereDate('expected_event_date', $eventDate))
            ->whereNotIn('status', ['withdrawn', 'not_eligible'])->exists();
        if ($duplicate) {
            throw ValidationException::withMessages(['actual_event_date' => 'An active eligibility request already exists for this leave type and event date.']);
        }

        return DB::transaction(function () use ($actor, $data, $documents, $employee, $type): SpecialLeaveEligibilityRequest {
            $request = SpecialLeaveEligibilityRequest::create([
                'employee_id' => $employee->id, 'leave_type_id' => $type->id,
                'reference' => 'SLV-'.now(config('attendance.timezone'))->format('Y').'-'.Str::upper(Str::random(10)),
                'event_category' => $data['event_category'], 'expected_event_date' => $data['expected_event_date'] ?? null,
                'actual_event_date' => $data['actual_event_date'] ?? null, 'status' => 'pending_hr_review',
                'additional_information' => $data['additional_information'], 'employee_declaration' => true, 'submitted_at' => now(),
            ]);
            $this->storeDocuments($request, $actor, $documents);
            $this->activity($request, $actor, 'submitted', null, 'pending_hr_review');
            $this->audit->record('special_leave.eligibility_submitted', $request, ['leave_type_id' => $type->id]);
            $this->notices->hr('leave', 'A special leave eligibility request needs HR review.', route('hr.leave.eligibility.show', $request), $request->id);
            $this->notices->send($actor, 'leave', 'Your special leave eligibility request was submitted.', route('employee.leave.eligibility.show', $request), $request->id);

            return $request;
        }, 3);
    }

    public function resubmit(User $actor, SpecialLeaveEligibilityRequest $target, array $data, array $documents): SpecialLeaveEligibilityRequest
    {
        return DB::transaction(function () use ($actor, $target, $data, $documents): SpecialLeaveEligibilityRequest {
            $request = SpecialLeaveEligibilityRequest::whereKey($target->id)->lockForUpdate()->firstOrFail();
            abort_unless($actor->can('update', $request), 403);
            $categoryIsValid = $request->type->code === 'PATERNITY'
                ? in_array($data['event_category'], ['paternity_qualifying_event', 'other_for_hr_review'], true)
                : $data['event_category'] !== 'paternity_qualifying_event';
            if (! $categoryIsValid) {
                throw ValidationException::withMessages(['event_category' => 'Choose an event category that matches the selected special leave type.']);
            }
            $previous = $request->status;
            $request->update(['event_category' => $data['event_category'], 'expected_event_date' => $data['expected_event_date'] ?? null, 'actual_event_date' => $data['actual_event_date'] ?? null, 'additional_information' => $data['additional_information'], 'status' => 'pending_hr_review', 'submitted_at' => now(), 'last_resubmitted_at' => now(), 'employee_declaration' => true]);
            $this->storeDocuments($request, $actor, $documents);
            $this->activity($request, $actor, 'resubmitted', $previous, 'pending_hr_review');
            $this->audit->record('special_leave.eligibility_resubmitted', $request);
            $this->notices->hr('leave', 'A special leave eligibility request was resubmitted.', route('hr.leave.eligibility.show', $request), $request->id);

            return $request;
        }, 3);
    }

    public function review(User $actor, SpecialLeaveEligibilityRequest $target, array $data): SpecialLeaveEligibilityRequest
    {
        abort_unless($actor->isHr(), 403);

        return DB::transaction(function () use ($actor, $target, $data): SpecialLeaveEligibilityRequest {
            $request = SpecialLeaveEligibilityRequest::whereKey($target->id)->lockForUpdate()->firstOrFail();
            abort_unless(in_array($request->status, ['pending_hr_review', 'additional_information_required'], true), 409, 'This eligibility request already has a final decision.');
            $previous = $request->status;
            $request->update(['status' => $data['decision'], 'reviewed_by_user_id' => $actor->id, 'reviewed_at' => now(), 'hr_remarks' => $data['remarks']]);
            if ($data['decision'] === 'verified') {
                $this->activateEntitlement($actor, $request);
            }
            $this->activity($request, $actor, $data['decision'], $previous, $data['decision'], $data['remarks']);
            $this->audit->record('special_leave.eligibility_'.$data['decision'], $request, ['previous_status' => $previous]);
            $this->notices->send($request->employee->user, 'leave', 'Your special leave eligibility request has been updated. Sign in to review its status.', route('employee.leave.eligibility.show', $request), $request->id);

            return $request;
        }, 3);
    }

    public function withdraw(User $actor, SpecialLeaveEligibilityRequest $target): void
    {
        DB::transaction(function () use ($actor, $target): void {
            $request = SpecialLeaveEligibilityRequest::whereKey($target->id)->lockForUpdate()->firstOrFail();
            abort_unless($actor->can('delete', $request), 403);
            $previous = $request->status;
            $request->update(['status' => 'withdrawn']);
            $this->activity($request, $actor, 'withdrawn', $previous, 'withdrawn');
            $this->audit->record('special_leave.eligibility_withdrawn', $request);
        }, 3);
    }

    private function activateEntitlement(User $actor, SpecialLeaveEligibilityRequest $request): void
    {
        $eventDate = CarbonImmutable::parse($request->actual_event_date ?? $request->expected_event_date);
        $policy = LeavePolicy::query()->where('leave_type_id', $request->leave_type_id)->where('allocation_basis', 'event')->where('is_active', true)
            ->whereDate('effective_from', '<=', $eventDate)->where(fn ($query) => $query->whereNull('effective_until')->orWhereDate('effective_until', '>=', $eventDate))->latest('effective_from')->firstOrFail();
        $rules = $policy->eligibility_rules ?? [];
        $variant = match ($request->event_category) {
            'live_childbirth_solo_parent' => 'solo_parent',
            'miscarriage_or_emergency_termination' => 'miscarriage_or_emergency_termination',
            default => 'standard',
        };
        $days = (float) match ($variant) {
            'solo_parent' => $rules['solo_parent_days'] ?? $policy->default_days,
            'miscarriage_or_emergency_termination' => $rules['miscarriage_or_emergency_termination_days'] ?? $policy->default_days,
            default => $rules['standard_days'] ?? $policy->default_days,
        };
        $entitlement = $this->balances->grant($actor, $request->employee, $policy, [
            'source' => 'event_grant', 'reference' => $request->reference,
            'period_start' => $eventDate->startOfYear()->toDateString(), 'period_end' => $eventDate->endOfYear()->toDateString(),
            'days' => $days, 'event_date' => $eventDate->toDateString(), 'event_variant' => $variant,
            'eligibility_status' => 'verified', 'verification_notes' => $request->hr_remarks,
        ]);
        $entitlement->update(['special_leave_eligibility_request_id' => $request->id]);
    }

    private function storeDocuments(SpecialLeaveEligibilityRequest $request, User $actor, array $documents): void
    {
        foreach ($documents as $document) {
            if (! $document instanceof UploadedFile) {
                continue;
            }
            $path = 'eligibility/'.Str::uuid().'.bin';
            Storage::disk('leave')->put($path, Crypt::encryptString($document->getContent()));
            SpecialLeaveEligibilityDocument::create(['special_leave_eligibility_request_id' => $request->id, 'storage_path' => $path, 'original_name' => $document->getClientOriginalName(), 'mime_type' => $document->getMimeType(), 'size_bytes' => $document->getSize(), 'uploaded_by_user_id' => $actor->id]);
        }
    }

    private function activity(SpecialLeaveEligibilityRequest $request, User $actor, string $action, ?string $previous, string $next, ?string $remarks = null): void
    {
        SpecialLeaveEligibilityActivity::create(['special_leave_eligibility_request_id' => $request->id, 'actor_user_id' => $actor->id, 'action' => $action, 'previous_status' => $previous, 'new_status' => $next, 'remarks' => $remarks, 'created_at' => now()]);
    }
}
