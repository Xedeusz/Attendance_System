<?php

namespace Tests\Unit;

use App\Http\Controllers\HR\AttendanceReportController;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;

class AttendanceReportCsvTest extends TestCase
{
    #[TestWith(['=SUM(1,2)', "'=SUM(1,2)"])]
    #[TestWith([' +1', "' +1"])]
    #[TestWith(['-Employee', "'-Employee"])]
    #[TestWith(['@Employee', "'@Employee"])]
    #[TestWith(["\tText", "'\tText"])]
    #[TestWith(["\rText", "'\rText"])]
    #[TestWith(['＝Text', "'＝Text"])]
    #[TestWith(['Élodie', 'Élodie'])]
    #[TestWith([375, 375])]
    #[TestWith([null, null])]
    public function test_spreadsheet_text_is_escaped_and_numeric_minutes_are_preserved(string|int|null $input, string|int|null $expected): void
    {
        $this->assertSame($expected, AttendanceReportController::spreadsheetCell($input));
    }
}
