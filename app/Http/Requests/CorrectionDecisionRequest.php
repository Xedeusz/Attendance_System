<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CorrectionDecisionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->isHr();
    }

    public function rules(): array
    {
        return ['decision' => ['required', Rule::in(['approved', 'rejected'])], 'remarks' => ['required', 'string', 'min:10', 'max:2000'], 'confirmation' => ['accepted']];
    }
}
