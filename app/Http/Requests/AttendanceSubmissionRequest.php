<?php

namespace App\Http\Requests;

use App\Services\AuditService;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

class AttendanceSubmissionRequest extends FormRequest
{
    protected function failedValidation(Validator $validator): void
    {
        if ($this->user()?->employee) {
            app(AuditService::class)->record('attendance.validation_failed', $this->user()->employee, ['fields' => array_keys($validator->errors()->messages())]);
        }
        parent::failedValidation($validator);
    }

    public function authorize(): bool
    {
        return $this->user()?->canAuthenticate() ?? false;
    }

    public static function locationRules(): array
    {
        return [
            'latitude' => ['nullable', 'required_with:longitude,accuracy,retrieved_at', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'required_with:latitude,accuracy,retrieved_at', 'numeric', 'between:-180,180'],
            'accuracy' => ['nullable', 'required_with:latitude,longitude,retrieved_at', 'numeric', 'min:0.1', 'max:100000'],
            'retrieved_at' => ['nullable', 'required_with:latitude,longitude,accuracy', 'integer', 'min:1'],
        ];
    }

    public function rules(): array
    {
        return [...self::locationRules(),
            'token' => ['required', 'string', 'regex:/^[a-f0-9]{64}$/'],
            'photo' => ['required', 'file', 'max:2048', 'mimetypes:image/jpeg,image/png'],
            'request_review' => ['sometimes', 'boolean'], 'exception_reason' => ['nullable', 'required_if:request_review,1', 'string', 'min:10', 'max:1000'],
            'employee_id' => ['prohibited'], 'user_id' => ['prohibited'], 'branch_id' => ['prohibited'],
            'occurred_at' => ['prohibited'], 'time_in_at' => ['prohibited'], 'time_out_at' => ['prohibited'],
            'verification_status' => ['prohibited'], 'geofence_status' => ['prohibited'],
        ];
    }
}
