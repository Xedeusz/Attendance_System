<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class EmployeeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isHr() ?? false;
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('work_email'))) {
            $this->merge(['work_email' => mb_strtolower(trim($this->input('work_email')))]);
        }
    }

    public function rules(): array
    {
        $employee = $this->route('employee');

        return [
            'employee_number' => ['required', 'string', 'max:40', 'regex:/^[A-Za-z0-9_-]+$/', Rule::unique('employees')->ignore($employee)],
            'first_name' => ['required', 'string', 'max:100'], 'last_name' => ['required', 'string', 'max:100'],
            'middle_name' => ['nullable', 'string', 'max:100'], 'suffix' => ['nullable', 'string', 'max:20'],
            'work_email' => ['required', 'email', 'max:254', Rule::unique('users', 'email')->ignore($employee?->user_id)],
            'contact_number' => ['nullable', 'string', 'max:30', 'regex:/^\+?[0-9 ()-]{7,30}$/'],
            'branch_id' => ['required', 'integer', Rule::exists('branches', 'id')->where('status', 'active')],
            'department_id' => ['required', 'integer', Rule::exists('departments', 'id')->where('status', 'active')],
            'position_title' => ['required', 'string', 'max:150'], 'date_hired' => ['required', 'date_format:Y-m-d'],
            'employment_status' => ['required', Rule::in(['active', 'inactive'])],
            'role' => ['prohibited'], 'role_id' => ['prohibited'], 'user_id' => ['prohibited'], 'account_status' => ['prohibited'], 'password' => ['prohibited'],
        ];
    }
}
