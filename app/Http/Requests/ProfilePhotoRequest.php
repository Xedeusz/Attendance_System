<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ProfilePhotoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isEmployee() && $this->user()->employee && $this->user()->canAuthenticate();
    }

    public function rules(): array
    {
        $rules = ['photo' => ['required', 'file', 'max:2048', 'mimetypes:image/jpeg,image/png,image/webp']];
        foreach (['user_id', 'employee_id', 'profile_photo_path', 'role', 'role_id', 'branch_id', 'department_id', 'employment_status', 'account_status', 'name', 'employee_number', 'supervisor_department_id'] as $field) {
            $rules[$field] = ['prohibited'];
        }

        return $rules;
    }

    public function messages(): array
    {
        return ['photo.max' => 'Profile pictures must not exceed 2 MB.', 'photo.mimetypes' => 'Please upload a valid JPG, PNG, or WebP image.'];
    }
}
