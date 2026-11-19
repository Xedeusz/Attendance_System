<?php

namespace App\Http\Requests;

use App\Models\WorkSchedule;
use App\Services\WorkDuration;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class WorkScheduleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->isHr();
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:150'], 'description' => ['nullable', 'string', 'max:2000'],
            'starts_at' => ['required', 'date_format:H:i'], 'ends_at' => ['required', 'date_format:H:i', 'different:starts_at'],
            'break_starts_at' => ['nullable', 'required_with:break_ends_at', 'date_format:H:i'],
            'break_ends_at' => ['nullable', 'required_with:break_starts_at', 'date_format:H:i'],
            'paid_break' => ['required', 'boolean'],
            'grace_minutes' => ['required', 'integer', 'between:0,1439'], 'grace_method' => ['required', Rule::in(array_keys(WorkSchedule::GRACE_METHODS))],
            'working_days' => ['required', 'array', 'list', 'max:7'], 'working_days.*' => ['required', 'distinct', 'integer', 'between:1,7'],
            'effective_from' => ['required', 'date_format:Y-m-d'],
            'effective_until' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:effective_from'],
            'scope' => ['required', Rule::in(['company', 'employee', 'department', 'branch'])],
            'employee_id' => ['exclude_unless:scope,employee', 'required_without:employee_ids', 'nullable', 'integer', 'exists:employees,id'],
            'department_id' => ['exclude_unless:scope,department', 'required_without:department_ids', 'nullable', 'integer', 'exists:departments,id'],
            'branch_id' => ['exclude_unless:scope,branch', 'required_without:branch_ids', 'nullable', 'integer', 'exists:branches,id'],
            'employee_ids' => ['exclude_unless:scope,employee', 'array', 'list', 'min:1', 'max:100'],
            'employee_ids.*' => ['required', 'integer', 'distinct', 'exists:employees,id'],
            'department_ids' => ['exclude_unless:scope,department', 'array', 'list', 'min:1', 'max:100'],
            'department_ids.*' => ['required', 'integer', 'distinct', 'exists:departments,id'],
            'branch_ids' => ['exclude_unless:scope,branch', 'array', 'list', 'min:1', 'max:100'],
            'branch_ids.*' => ['required', 'integer', 'distinct', 'exists:branches,id'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty() || ! $this->filled('break_starts_at')) {
                return;
            }

            $start = $this->input('starts_at');
            $breakStart = WorkDuration::offset($this->input('break_starts_at'), $start);
            $breakEnd = WorkDuration::offset($this->input('break_ends_at'), $start);
            $end = WorkDuration::offset($this->input('ends_at'), $start);

            if ($breakEnd <= $breakStart || $breakEnd > $end) {
                $validator->errors()->add('break_ends_at', 'The break must start and end in order within the work shift.');
            }
        }];
    }
}
