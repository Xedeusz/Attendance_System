<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SupervisorAccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->isHr();
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('email'))) {
            $this->merge(['email' => mb_strtolower(trim($this->input('email')))]);
        }
    }

    public function rules(): array
    {
        return ['first_name' => ['required_without:employee_id', 'nullable', 'string', 'max:100'], 'last_name' => ['required_without:employee_id', 'nullable', 'string', 'max:100'], 'email' => ['required', 'email', 'max:254'], 'employee_id' => ['nullable', 'integer', 'exists:employees,id'], 'account_status' => ['required', Rule::in(['active', 'pending_activation', 'deactivated'])], 'role_id' => ['prohibited'], 'role' => ['prohibited'], 'password' => ['prohibited']];
    }
}
