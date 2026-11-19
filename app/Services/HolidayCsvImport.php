<?php

namespace App\Services;

use App\Models\AttendanceHoliday;
use Carbon\CarbonImmutable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class HolidayCsvImport
{
    /** @return list<array{holiday_date: string, name: string, type: string, work_expected: bool}> */
    public function read(UploadedFile $file, int $year): array
    {
        $handle = fopen($file->getPathname(), 'rb');
        $rows = [];
        $identities = [];
        try {
            $header = fgetcsv($handle, escape: '');
            if ($header) {
                $header[0] = ltrim((string) $header[0], "\xEF\xBB\xBF");
            }
            if ($header !== ['date', 'name', 'type', 'work_expected']) {
                $this->fail('Use the exact CSV headers: date,name,type,work_expected.');
            }
            $line = 1;
            while (($columns = fgetcsv($handle, escape: '')) !== false) {
                $line++;
                if ($columns === [null]) {
                    continue;
                }
                if (count($columns) !== 4 || count($rows) >= 200 || ! mb_check_encoding($columns, 'UTF-8')) {
                    $this->fail("Invalid CSV row {$line}, or the 200-row limit was exceeded.");
                }
                [$date, $name, $type, $expected] = array_map('trim', $columns);
                $row = ['holiday_date' => $date, 'name' => $name, 'type' => $type, 'work_expected' => $expected];
                $check = Validator::make($row, [
                    'holiday_date' => ['required', 'date_format:Y-m-d', 'regex:/^'.$year.'-/'],
                    'name' => ['required', 'string', 'max:150', 'not_regex:/[\x00-\x1F\x7F]/'],
                    'type' => ['required', Rule::in(['regular', 'special_non_working', 'special_working'])],
                    'work_expected' => ['required', 'boolean'],
                ]);
                if ($check->fails()) {
                    $this->fail("CSV row {$line}: ".$check->errors()->first());
                }
                $identity = self::identity($date, $name, 'company');
                if (isset($identities[$identity])) {
                    $this->fail("CSV row {$line} duplicates another row for the same holiday.");
                }
                $identities[$identity] = true;
                $rows[] = [...$row, 'work_expected' => (bool) $expected];
            }
        } finally {
            fclose($handle);
        }

        if ($rows === []) {
            $this->fail('The CSV must contain at least one holiday.');
        }

        return $rows;
    }

    public static function identity(string $date, string $name, string $scope, array $coverage = []): string
    {
        sort($coverage, SORT_STRING);
        $normalized = preg_replace('/\s+/u', ' ', mb_strtolower(trim($name)));

        return hash('sha256', json_encode([$date, $normalized, $scope, array_map('strval', $coverage)], JSON_THROW_ON_ERROR));
    }

    public function statuses(array $rows): array
    {
        $dates = array_column($rows, 'holiday_date');
        $existing = AttendanceHoliday::where('holiday_date', '>=', min($dates))
            ->where('holiday_date', '<', CarbonImmutable::parse(max($dates))->addDay()->toDateString())->get()
            ->groupBy(fn (AttendanceHoliday $holiday) => $holiday->holiday_date->toDateString());
        $proposed = collect($rows)->groupBy('holiday_date');

        return array_map(function (array $row) use ($existing, $proposed): array {
            $sameDate = $existing->get($row['holiday_date'], collect());
            $duplicate = $sameDate->contains('identity_hash', self::identity($row['holiday_date'], $row['name'], 'company'));
            $conflict = $sameDate->contains(fn (AttendanceHoliday $holiday) => $holiday->status === 'active'
                && ($holiday->type !== $row['type'] || $holiday->work_expected !== $row['work_expected']))
                || $proposed[$row['holiday_date']]->contains(fn (array $other): bool => $other['type'] !== $row['type'] || $other['work_expected'] !== $row['work_expected']);

            return ['duplicate' => $duplicate, 'conflict' => $conflict];
        }, $rows);
    }

    private function fail(string $message): never
    {
        throw ValidationException::withMessages(['csv' => $message]);
    }
}
