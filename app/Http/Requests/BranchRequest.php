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
            'latitude' => ['nullable', 'required_if:status,active', 'required_with:longitude,geofence_radius_meters', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'required_if:status,active', 'required_with:latitude,geofence_radius_meters', 'numeric', 'between:-180,180'],
            'geofence_radius_meters' => ['nullable', 'required_if:status,active', 'required_with:latitude,longitude', 'integer', 'min:'.config('attendance.geofence_min_radius_meters'), 'max:'.config('attendance.geofence_max_radius_meters')],
            'status' => ['required', Rule::in(['active', 'inactive'])],
        ];
    }

    public function messages(): array
    {
        return [
            'latitude.required_if' => 'Please select the branch location on the map.',
            'longitude.required_if' => 'Please select the branch location on the map.',
            'geofence_radius_meters.required_if' => 'Enter a valid geofence radius in meters.',
            'geofence_radius_meters.required_with' => 'Enter a valid geofence radius in meters.',
        ];
    }
}
