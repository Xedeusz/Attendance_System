<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SupervisorDepartmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isHr() ?? false;
    }

    public function rules(): array
    {
        return ['supervisor_department_id' => ['required', 'integer', Rule::exists('departments', 'id')->where('status', 'active')], '_edit_version' => ['required', 'string', 'size:64']];
    }
}
