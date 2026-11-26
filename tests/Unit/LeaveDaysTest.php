<?php

namespace Tests\Unit;

use App\Support\LeaveDays;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class LeaveDaysTest extends TestCase
{
    /**
     * @return array<string, array{int|float|string|null, string}>
     */
    public static function leaveDayValues(): array
    {
        return [
            'integer float' => [15.0, '15'],
            'decimal database string' => ['15.00', '15'],
            'zero' => [0, '0'],
            'half day' => [7.5, '7.5'],
            'quarter day precision' => ['7.25', '7.25'],
            'negative adjustment' => [-2.0, '-2'],
            'missing value' => [null, '—'],
        ];
    }

    #[DataProvider('leaveDayValues')]
    public function test_it_removes_only_insignificant_decimal_places(int|float|string|null $value, string $expected): void
    {
        $this->assertSame($expected, LeaveDays::format($value));
    }

    public function test_it_uses_the_correct_day_unit(): void
    {
        $this->assertSame('1 day', LeaveDays::withUnit('1.00'));
        $this->assertSame('0.5 days', LeaveDays::withUnit('0.50'));
        $this->assertSame('15 days', LeaveDays::withUnit(15));
    }
}
