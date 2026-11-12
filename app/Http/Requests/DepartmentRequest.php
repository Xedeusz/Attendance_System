<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DepartmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isHr() ?? false;
    }

    public function rules(): array
    {
        return [
            'department_code' => ['required', 'string', 'max:30', 'regex:/^[A-Za-z0-9_-]+$/', Rule::unique('departments')->ignore($this->route('department'))],
            'department_name' => ['required', 'string', 'max:150'], 'status' => ['required', Rule::in(['active', 'inactive'])],
        ];
    }
}
