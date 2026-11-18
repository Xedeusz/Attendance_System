<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SupervisorSignatureRequest extends FormRequest
{
    public function authorize(): bool
    {
        $actor = $this->user();

        return $actor && $actor->canAuthenticate() && $actor->isSupervisor();
    }

    public function rules(): array
    {
        return [
            'current_password' => ['required', 'string', 'current_password'],
            'signature' => ['required', 'file', 'mimes:png,jpg,jpeg', 'extensions:png,jpg,jpeg', 'max:2048'],
        ];
    }
}
