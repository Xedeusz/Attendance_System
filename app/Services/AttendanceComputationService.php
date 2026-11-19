<?php

namespace App\Services;

use App\Models\AttendanceComputation;
use App\Models\AttendanceCorrectionRequest;
use App\Models\AttendanceRecord;
use App\Models\Branch;
use App\Models\Employee;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;

class AttendanceComputationService
{
    public function __construct(private AttendancePolicyResolver $resolver, private WorkDuration $duration) {}

    public function initialPolicy(Employee $employee, Branch $branch, CarbonInterface $timeIn): array
    {
        $arrival = $timeIn->copy()->setTimezone(config('attendance.timezone'));
        $today = $this->resolver->resolve($employee, $arrival->toDateString(), $branch);
        if ($today['obligation'] === 'work') {
            $shift = $this->duration->shift($today['date'], $today['schedule'], $today['timezone'], $today['adjusted_end']);
            if ($arrival->gte($shift['start']) && $arrival->lt($shift['end'])) {
                return $today;
            }
        }

        $previous = $this->resolver->resolve($employee, $arrival->copy()->subDay()->toDateString(), $branch);
        if ($previous['obligation'] === 'work') {
            $shift = $this->duration->shift($previous['date'], $previous['schedule'], $previous['timezone'], $previous['adjusted_end']);
            if ($arrival->lt($shift['end'])) {
                return $previous;
            }
        }

        return $today;
    }

    public function capture(AttendanceRecord $record, string $trigger = 'attendance', ?array $initialPolicy = null): void
    {
        $previous = $record->computations()->latest('version')->first();
        $policy = $previous?->policy ?? $initialPolicy ?? $this->legacyPolicy($record);
        [$in, $out] = $this->times($record);

        $this->append($record->computations(), $previous, $policy, $in, $out, $trigger);
    }

    public function captureManual(AttendanceCorrectionRequest $correction): void
    {
        $date = $correction->requested_date->toDateString();
        $branch = $this->resolver->branchFor($correction->employee, $date);
        $policy = $this->resolver->resolve($correction->employee, $date, $branch);
        $this->append($correction->computations(), null, $policy, $correction->requested_time_in_at, $correction->requested_time_out_at, 'correction');
    }

    /** @param Collection<int, AttendanceRecord|AttendanceCorrectionRequest> $records */
    public function day(Collection $records): ?AttendanceComputation
    {
        $trusted = $records->filter(fn ($record) => $this->times($record)[0] !== null)
            ->sortBy(fn ($record) => $this->times($record)[0])->values();
        $first = $trusted->first();
        if (! $first) {
            return null;
        }
        if ($trusted->count() === 1 && $first->computation) {
            return $first->computation;
        }

        $policy = $first->computation?->policy ?? $this->legacyPolicy($first);
        $intervals = $trusted->map(fn ($record) => $this->times($record))->all();

        return new AttendanceComputation(['policy' => $policy, ...$this->duration->calculateIntervals($policy, $intervals)]);
    }

    private function legacyPolicy(AttendanceRecord|AttendanceCorrectionRequest $record): array
    {
        $date = $record instanceof AttendanceRecord ? $record->work_date : $record->requested_date;

        return $this->resolver->withoutPolicy($date->toDateString(), $record->branch_id, AttendancePolicyResolver::location($record->branch));
    }

    /** @return array{?CarbonInterface, ?CarbonInterface} */
    private function times(AttendanceRecord|AttendanceCorrectionRequest $record): array
    {
        return $record instanceof AttendanceRecord
            ? [$record->effective_time_in_at ?? $record->time_in_at, $record->effective_time_out_at ?? $record->time_out_at]
            : [$record->requested_time_in_at, $record->requested_time_out_at];
    }

    private function payload(array $policy, ?CarbonInterface $in, ?CarbonInterface $out): array
    {
        return [
            'policy' => $policy, 'work_schedule_id' => $policy['schedule']['id'] ?? null,
            'holiday_types' => array_values(array_unique(array_column($policy['holidays'], 'type'))),
            'timestamps_used' => ['in' => $in?->format('Y-m-d\TH:i:s.uP'), 'out' => $out?->format('Y-m-d\TH:i:s.uP')],
            ...$this->duration->calculate($policy, $in, $out),
        ];
    }

    private function append(HasMany $relation, ?AttendanceComputation $previous, array $policy, ?CarbonInterface $in, ?CarbonInterface $out, string $trigger, ?string $reason = null): AttendanceComputation
    {
        $payload = $this->payload($policy, $in, $out);
        if ($previous && Arr::sortRecursive($previous->only(array_keys($payload))) === Arr::sortRecursive($payload)) {
            return $previous;
        }

        return $relation->create([
            ...$payload, 'version' => 1 + ($previous?->version ?? 0), 'trigger' => $trigger,
            'reason' => $reason, 'actor_id' => auth()->id(),
        ]);
    }
}
