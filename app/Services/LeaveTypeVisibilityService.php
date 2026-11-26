<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\LeaveEntitlement;
use App\Models\LeaveType;
use App\Models\SpecialLeaveEligibilityRequest;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class LeaveTypeVisibilityService
{
    private const SPECIAL_TYPES = [
        'MATERNITY' => 'female',
        'PATERNITY' => 'male',
    ];

    /** @param Collection<int, LeaveType> $types
     * @return Collection<int, LeaveType>
     */
    public function applicableTypes(Employee $employee, Collection $types): Collection
    {
        $specialTypeIds = $types->whereIn('code', array_keys(self::SPECIAL_TYPES))->pluck('id');
        $exceptionTypeIds = $this->exceptionTypeIds($employee, $specialTypeIds);

        return $types->filter(fn (LeaveType $type): bool => $this->isApplicable($employee, $type, $exceptionTypeIds))->values();
    }

    public function canInitiateVerification(Employee $employee, LeaveType $type): bool
    {
        return isset(self::SPECIAL_TYPES[$type->code])
            && $employee->sex === self::SPECIAL_TYPES[$type->code];
    }

    public function assertApplicable(Employee $employee, LeaveType $type): void
    {
        if (! isset(self::SPECIAL_TYPES[$type->code])) {
            return;
        }

        $exceptionTypeIds = $this->exceptionTypeIds($employee, collect([$type->id]));
        if (! $this->isApplicable($employee, $type, $exceptionTypeIds)) {
            throw ValidationException::withMessages([
                'leave_type_id' => 'This leave type is not available for your current eligibility record. Please contact HR for verification.',
            ]);
        }
    }

    /** @param Collection<int, int> $specialTypeIds
     * @return Collection<int, int>
     */
    private function exceptionTypeIds(Employee $employee, Collection $specialTypeIds): Collection
    {
        if ($specialTypeIds->isEmpty()) {
            return collect();
        }

        $requestTypeIds = SpecialLeaveEligibilityRequest::query()
            ->whereBelongsTo($employee)
            ->whereIn('leave_type_id', $specialTypeIds)
            ->whereIn('status', ['pending_hr_review', 'additional_information_required', 'verified'])
            ->pluck('leave_type_id');
        $entitlementTypeIds = LeaveEntitlement::query()
            ->whereBelongsTo($employee)
            ->whereIn('leave_type_id', $specialTypeIds)
            ->where('eligibility_status', 'verified')
            ->whereDate('period_end', '>=', now(config('attendance.timezone'))->toDateString())
            ->pluck('leave_type_id');

        return $requestTypeIds->merge($entitlementTypeIds)->unique()->values();
    }

    /** @param Collection<int, int> $exceptionTypeIds */
    private function isApplicable(Employee $employee, LeaveType $type, Collection $exceptionTypeIds): bool
    {
        $requiredSex = self::SPECIAL_TYPES[$type->code] ?? null;

        return $requiredSex === null || $employee->sex === $requiredSex || $exceptionTypeIds->contains($type->id);
    }
}
