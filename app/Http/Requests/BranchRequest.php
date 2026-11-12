<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BranchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isHr() ?? false;
    }

    public function rules(): array
    {
        return [
            'branch_code' => ['required', 'string', 'max:30', 'regex:/^[A-Za-z0-9_-]+$/', Rule::unique('branches')->ignore($this->route('branch'))],
            'branch_name' => ['required', 'string', 'max:150'], 'address' => ['required', 'string', 'max:255'],
            'city' => ['required', 'string', 'max:100'], 'province' => ['required', 'string', 'max:100'], 'postal_code' => ['nullable', 'string', 'max:20'],
            'latitude' => ['nullable', 'required_with:longitude', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'required_with:latitude', 'numeric', 'between:-180,180'],
            'geofence_radius_meters' => ['nullable', 'integer', 'min:1', 'max:100000'],
            'status' => ['required', Rule::in(['active', 'inactive'])],
        ];
    }
}
