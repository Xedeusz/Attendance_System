<?php

namespace App\Services;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

class WorkDuration
{
    public static function seconds(string $time): int
    {
        $parts = array_map('intval', explode(':', $time));

        return $parts[0] * 3600 + $parts[1] * 60 + ($parts[2] ?? 0);
    }

    public static function offset(string $time, string $start): int
    {
        return (self::seconds($time) - self::seconds($start) + 86400) % 86400;
    }

    /** @return array{start: CarbonImmutable, end: CarbonImmutable, break_start: ?CarbonImmutable, break_end: ?CarbonImmutable} */
    public function shift(string $date, array $schedule, string $timezone, ?string $adjustedEnd = null): array
    {
        $startTime = $schedule['starts_at'];
        $start = CarbonImmutable::parse($date.' '.$startTime, $timezone);
        $end = $start->addSeconds(self::offset($adjustedEnd ?? $schedule['ends_at'], $startTime));
        $unpaid = ! $schedule['paid_break'] && isset($schedule['break_starts_at'], $schedule['break_ends_at']);

        return [
            'start' => $start, 'end' => $end,
            'break_start' => $unpaid ? $start->addSeconds(self::offset($schedule['break_starts_at'], $startTime)) : null,
            'break_end' => $unpaid ? $start->addSeconds(self::offset($schedule['break_ends_at'], $startTime)) : null,
        ];
    }

    public function calculate(array $policy, ?CarbonInterface $in, ?CarbonInterface $out): array
    {
        return $this->calculateIntervals($policy, [[$in, $out]]);
    }

    /** @param list<array{?CarbonInterface, ?CarbonInterface}> $intervals */
    public function calculateIntervals(array $policy, array $intervals): array
    {
        $schedule = $policy['schedule'];
        $shift = $schedule ? $this->shift($policy['date'], $schedule, $policy['timezone'], $policy['adjusted_end']) : null;
        $arrivals = array_values(array_filter($intervals, fn (array $interval): bool => $interval[0] !== null));
        usort($arrivals, fn (array $first, array $second): int => $first[0] <=> $second[0]);
        $in = $arrivals[0][0] ?? null;
        $merged = [];
        $incomplete = false;
        foreach ($arrivals as [$start, $end]) {
            if (! $end || $end->lt($start)) {
                $incomplete = true;

                continue;
            }
            $previous = array_key_last($merged);
            if ($previous !== null && $start->lte($merged[$previous][1])) {
                $merged[$previous][1] = max($end, $merged[$previous][1]);
            } else {
                $merged[] = [$start, $end];
            }
        }

        $complete = $in !== null && ! $incomplete;
        $out = $complete ? $merged[array_key_last($merged)][1] : null;
        $elapsed = $break = 0.0;
        $required = $policy['obligation'] === 'work';
        foreach ($merged as [$start, $end]) {
            $elapsed += $start->diffInSeconds($end);
            if ($required) {
                $break += $this->overlap($start, $end, $shift);
            }
        }
        $expected = match ($policy['obligation']) {
            'work' => (int) floor(($shift['start']->diffInSeconds($shift['end']) - $this->overlap($shift['start'], $shift['end'], $shift)) / 60),
            'not_scheduled', 'excused', 'approved_leave' => 0,
            default => null,
        };
        $partial = $policy['partial_leave'];
        $late = $required && $in && $partial !== 'am' ? $this->late($in, $shift['start'], $schedule) : null;
        $undertime = $required && $complete && $partial !== 'pm'
            ? (int) floor(max(0, $out->diffInSeconds($shift['end'])) / 60)
            : null;
        if ($partial && $required) {
            $expected = null;
        }

        return [
            'expected_minutes' => $expected,
            'recorded_minutes' => $complete ? (int) floor($elapsed / 60) : null,
            'unpaid_break_minutes' => $complete ? (int) floor($break / 60) : null,
            'net_minutes' => $complete ? (int) floor(max(0, $elapsed - $break) / 60) : null,
            'late_minutes' => $late,
            'undertime_minutes' => $undertime,
            'incomplete' => $incomplete,
        ];
    }

    private function overlap(CarbonInterface $start, CarbonInterface $end, array $shift): float
    {
        if (! $shift['break_start']) {
            return 0;
        }

        $first = $start->gt($shift['break_start']) ? $start : $shift['break_start'];
        $last = $end->lt($shift['break_end']) ? $end : $shift['break_end'];

        return max(0, $first->diffInSeconds($last));
    }

    private function late(CarbonInterface $actual, CarbonInterface $start, array $schedule): int
    {
        $seconds = $start->diffInSeconds($actual);
        $grace = $schedule['grace_minutes'] * 60;

        if ($seconds <= $grace) {
            return 0;
        }

        return (int) floor(($seconds - ($schedule['grace_method'] === 'after_grace' ? $grace : 0)) / 60);
    }
}
