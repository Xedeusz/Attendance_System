<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class LeaveDecisionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('review', $this->route('leave'));
    }

    public function rules(): array
    {
        return [
            'decision' => ['required', Rule::in(['approved', 'rejected'])],
            'remarks' => ['required_if:decision,rejected', 'nullable', 'string', 'min:10', 'max:2000'],
            'chargeable_days' => ['exclude_unless:decision,approved', 'nullable', 'numeric', 'min:0', 'max:366', 'multiple_of:0.5'],
            'current_password' => ['exclude_unless:decision,approved', 'required', 'string', 'current_password'],
            'signature_version' => ['exclude_unless:decision,approved', 'required', 'integer', 'min:1'],
            'confirmation' => ['accepted'],
        ];
    }
}
