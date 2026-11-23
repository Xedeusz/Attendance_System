<?php

namespace App\Http\Controllers\HR;

use App\Http\Controllers\Controller;
use App\Http\Requests\AttendanceReportRequest;
use App\Models\Branch;
use App\Models\Department;
use App\Models\Employee;
use App\Services\AttendanceReportingService;
use Illuminate\View\View;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class AttendanceReportController extends Controller
{
    private const CSV_COLUMNS = [
        'date' => 'Date', 'employee_number' => 'Employee ID', 'employee' => 'Employee Name',
        'branch' => 'Branch', 'department' => 'Department', 'schedule' => 'Schedule',
        'in' => 'Time In', 'out' => 'Time Out', 'minutes' => 'Work Hours (Minutes)',
        'late' => 'Late (Minutes)', 'undertime' => 'Undertime (Minutes)',
        'status' => 'Attendance Status', 'leave' => 'Leave Status',
    ];

    public function index(AttendanceReportRequest $request, AttendanceReportingService $reports): View
    {
        $filters = $request->filters();
        [$summary, $rows] = $reports->report($filters, $request->integer('page', 1));

        return view('attendance.reports', [
            'filters' => $filters, 'summary' => $summary, 'rows' => $rows,
            'branches' => Branch::orderBy('branch_name')->pluck('branch_name', 'id'),
            'departments' => Department::orderBy('department_name')->pluck('department_name', 'id'),
            'employees' => $reports->employeeOptions($filters),
        ]);
    }

    public function show(AttendanceReportRequest $request, Employee $employee, string $date, AttendanceReportingService $reports): View
    {
        $filters = $request->filters();
        abort_if($date < $filters['from'] || $date > $filters['to']
            || (isset($filters['employee_id']) && (int) $filters['employee_id'] !== $employee->id), 404);
        $parts = array_map('intval', explode('-', $date));
        abort_unless(checkdate($parts[1], $parts[2], $parts[0]), 404);

        $rows = $reports->rows([...$filters, 'from' => $date, 'to' => $date, 'employee_id' => $employee->id]);
        $row = $rows->current();
        abort_unless($row, 404);
        $back = [...$filters, 'page' => $request->integer('page', 1)];

        return view('attendance.report-day', compact('row', 'back'));
    }

    public function export(AttendanceReportRequest $request, AttendanceReportingService $reports): StreamedResponse
    {
        $filters = $request->filters();
        $stream = fopen('php://temp', 'w+b');
        if ($stream === false) {
            throw new RuntimeException('Unable to open the attendance export stream.');
        }

        try {
            $this->writeCsv($stream, array_values(self::CSV_COLUMNS));
            foreach ($reports->rows($filters) as $row) {
                $this->writeCsv($stream, array_map(
                    fn (string $field): string|int|null => self::spreadsheetCell($row[$field]),
                    array_keys(self::CSV_COLUMNS),
                ));
            }
            if (! rewind($stream)) {
                throw new RuntimeException('Unable to read the attendance export stream.');
            }
        } catch (Throwable $error) {
            fclose($stream);

            throw $error;
        }

        return response()->streamDownload(function () use ($stream): void {
            try {
                fpassthru($stream);
            } finally {
                fclose($stream);
            }
        }, "LIMCOMA_Attendance_Report_{$filters['from']}_to_{$filters['to']}.csv", [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    public static function spreadsheetCell(string|int|null $value): string|int|null
    {
        return is_string($value) && preg_match('/^(?:[\s\p{Z}\p{Cf}]*[=+\-@＝＋－＠]|[\t\r\n])/u', $value)
            ? "'".$value
            : $value;
    }

    /** @param resource $stream */
    private function writeCsv($stream, array $cells): void
    {
        if (fputcsv($stream, $cells, ',', '"', '', "\r\n") === false) {
            throw new RuntimeException('Unable to write the attendance export.');
        }
    }
}
