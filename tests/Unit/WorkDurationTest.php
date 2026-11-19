<?php

namespace Tests\Unit;

use App\Services\WorkDuration;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class WorkDurationTest extends TestCase
{
    #[DataProvider('sessions')]
    public function test_calculation_uses_actual_break_overlap_and_shift_boundaries(string $in, ?string $out, array $overrides, array $expected): void
    {
        $schedule = [
            'starts_at' => '08:00', 'ends_at' => '16:00', 'break_starts_at' => '12:00', 'break_ends_at' => '13:00',
            'paid_break' => false, 'grace_minutes' => 10, 'grace_method' => 'from_start',
        ];
        $policy = ['schedule' => [...$schedule, ...$overrides], 'date' => '2026-11-19', 'timezone' => 'Asia/Manila', 'adjusted_end' => null, 'obligation' => 'work', 'partial_leave' => null];

        $actual = (new WorkDuration)->calculate($policy, CarbonImmutable::parse($in, 'Asia/Manila'), $out ? CarbonImmutable::parse($out, 'Asia/Manila') : null);

        $this->assertSame(array_combine(['expected_minutes', 'recorded_minutes', 'unpaid_break_minutes', 'net_minutes', 'late_minutes', 'undertime_minutes', 'incomplete'], $expected), $actual);
    }

    public static function sessions(): array
    {
        return [
            'on time' => ['2026-11-19 08:00', '2026-11-19 16:00', [], [420, 480, 60, 420, 0, 0, false]],
            'early arrival and no overtime' => ['2026-11-19 07:55', '2026-11-19 16:05', [], [420, 490, 60, 430, 0, 0, false]],
            'inside grace' => ['2026-11-19 08:05', '2026-11-19 16:00', [], [420, 475, 60, 415, 0, 0, false]],
            'exact grace boundary' => ['2026-11-19 08:10', '2026-11-19 16:00', [], [420, 470, 60, 410, 0, 0, false]],
            'grace exceeded from start' => ['2026-11-19 08:11', '2026-11-19 16:00', [], [420, 469, 60, 409, 11, 0, false]],
            'count only after grace' => ['2026-11-19 08:11', '2026-11-19 16:00', ['grace_method' => 'after_grace'], [420, 469, 60, 409, 1, 0, false]],
            'late and undertime' => ['2026-11-19 08:25', '2026-11-19 15:30', [], [420, 425, 60, 365, 25, 30, false]],
            'missing out' => ['2026-11-19 08:25', null, [], [420, null, null, null, 25, null, true]],
            'out before break' => ['2026-11-19 08:00', '2026-11-19 11:00', [], [420, 180, 0, 180, 0, 300, false]],
            'partial break' => ['2026-11-19 12:30', '2026-11-19 16:00', [], [420, 210, 30, 180, 270, 0, false]],
            'paid break' => ['2026-11-19 08:00', '2026-11-19 16:00', ['paid_break' => true], [480, 480, 0, 480, 0, 0, false]],
            'overnight break' => ['2026-11-19 23:20', '2026-11-20 06:40', [
                'starts_at' => '23:00', 'ends_at' => '07:00', 'break_starts_at' => '02:00', 'break_ends_at' => '03:00',
            ], [420, 440, 60, 380, 20, 20, false]],
        ];
    }

    public function test_overlapping_intervals_count_work_and_unpaid_break_only_once(): void
    {
        $policy = [
            'schedule' => ['starts_at' => '08:00', 'ends_at' => '16:00', 'break_starts_at' => '12:00', 'break_ends_at' => '13:00', 'paid_break' => false, 'grace_minutes' => 0, 'grace_method' => 'from_start'],
            'date' => '2026-11-19', 'timezone' => 'Asia/Manila', 'adjusted_end' => null, 'obligation' => 'work', 'partial_leave' => null,
        ];
        $time = fn (string $value) => CarbonImmutable::parse('2026-11-19 '.$value, 'Asia/Manila');

        $actual = (new WorkDuration)->calculateIntervals($policy, [
            [$time('12:30'), $time('16:00')], [$time('08:00'), $time('13:15')],
        ]);

        $this->assertSame(480, $actual['recorded_minutes']);
        $this->assertSame(60, $actual['unpaid_break_minutes']);
        $this->assertSame(420, $actual['net_minutes']);
        $this->assertSame(0, $actual['late_minutes']);
        $this->assertSame(0, $actual['undertime_minutes']);
    }

    public function test_an_approved_early_departure_before_lunch_shortens_expected_work(): void
    {
        $policy = [
            'schedule' => ['starts_at' => '08:00', 'ends_at' => '16:00', 'break_starts_at' => '12:00', 'break_ends_at' => '13:00', 'paid_break' => false, 'grace_minutes' => 0, 'grace_method' => 'from_start'],
            'date' => '2026-11-19', 'timezone' => 'Asia/Manila', 'adjusted_end' => '11:00', 'obligation' => 'work', 'partial_leave' => null,
        ];

        $actual = (new WorkDuration)->calculate($policy, CarbonImmutable::parse('2026-11-19 08:00', 'Asia/Manila'), CarbonImmutable::parse('2026-11-19 11:00', 'Asia/Manila'));

        $this->assertSame(180, $actual['expected_minutes']);
        $this->assertSame(0, $actual['undertime_minutes']);
    }
}
