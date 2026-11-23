<?php

namespace App\Http\Requests;

use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class AttendanceReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isHr() === true;
    }

    protected function prepareForValidation(): void
    {
        $month = CarbonImmutable::now(config('attendance.timezone'));
        $this->mergeIfMissing(['from' => $month->startOfMonth()->toDateString(), 'to' => $month->endOfMonth()->toDateString()]);
    }

    public function rules(): array
    {
        return [
            'from' => ['required', 'date_format:Y-m-d', 'after_or_equal:1900-01-01'],
            'to' => ['required', 'date_format:Y-m-d', 'after_or_equal:from', 'before_or_equal:2099-12-31'],
            'branch_id' => ['nullable', 'integer', 'exists:branches,id'],
            'department_id' => ['nullable', 'integer', 'exists:departments,id'],
            'employee_id' => ['nullable', 'integer', 'exists:employees,id'],
            'direction' => ['nullable', 'in:asc,desc'],
            'page' => ['nullable', 'integer', 'min:1', 'max:10000'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->hasAny(['from', 'to'])) {
                return;
            }

            $from = CarbonImmutable::parse($this->input('from'));
            if ($from->addDays(91)->lt(CarbonImmutable::parse($this->input('to')))) {
                $validator->errors()->add('to', 'Select a reporting period of 92 days or fewer.');
            }
        }];
    }

    protected function getRedirectUrl(): string
    {
        return route('hr.attendance-reports.index');
    }

    public function filters(): array
    {
        return array_filter($this->safe()->except('page'), fn (mixed $value): bool => $value !== null);
    }
}
