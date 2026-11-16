<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class LeaveDecisionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->isSupervisor();
    }

    public function rules(): array
    {
        return ['decision' => ['required', Rule::in(['approved', 'rejected'])], 'remarks' => ['required', 'string', 'min:10', 'max:2000'], 'chargeable_days' => ['nullable', 'numeric', 'min:0', 'max:366', 'multiple_of:0.5'], 'confirmation' => ['accepted']];
    }
}
