<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SupervisorAssignmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->isHr();
    }

    public function rules(): array
    {
        return ['employee_id' => ['required', 'integer', 'exists:employees,id'], 'effective_from' => ['required', 'date_format:Y-m-d'], 'effective_until' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:effective_from'], 'replace_existing' => ['sometimes', 'accepted']];
    }
}
