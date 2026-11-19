<?php

namespace App\Http\Requests;

use App\Models\AttendanceHoliday;
use App\Models\Branch;
use App\Services\AttendancePolicyResolver;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class AttendanceHolidayRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->isHr();
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:150'], 'holiday_date' => ['required', 'date_format:Y-m-d'],
            'type' => ['required', Rule::in(array_keys(AttendanceHoliday::TYPES))],
            'status' => ['required', Rule::in(array_keys(AttendanceHoliday::STATUSES))],
            'work_expected' => ['required', 'boolean'],
            'description' => ['nullable', 'string', 'max:2000'], 'declaration' => ['nullable', 'string', 'max:255'],
            'proclamation_number' => ['nullable', 'string', 'max:100'], 'source_url' => ['nullable', 'url:http,https', 'max:2048'],
            'scope' => ['required', Rule::in(['company', 'branches', 'locations'])],
            'branch_ids' => ['exclude_unless:scope,branches', 'required', 'array', 'list', 'max:500'],
            'branch_ids.*' => ['integer', 'distinct', 'exists:branches,id'],
            'locations' => ['exclude_unless:scope,locations', 'required', 'array', 'list', 'max:500'],
            'locations.*' => ['string', 'distinct', 'max:255'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }
            if ($this->input('type') === 'local' && $this->input('scope') === 'company') {
                $validator->errors()->add('scope', 'Local holidays require selected branches or locations.');
            }
            if ($this->input('scope') === 'locations') {
                $known = array_keys(AttendancePolicyResolver::locationOptions(Branch::get(['city', 'province'])));
                if (array_diff($this->input('locations'), $known)) {
                    $validator->errors()->add('locations', 'Choose locations from the branch directory.');
                }
            }
        }];
    }
}
