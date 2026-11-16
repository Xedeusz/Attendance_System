<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AttendanceCorrectionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->canAuthenticate() && ! $this->user()->isSupervisor();
    }

    public function rules(): array
    {
        return ['attendance_record_id' => ['nullable', 'integer'], 'correction_type' => ['required', Rule::in(['forgotten_time_in', 'forgotten_time_out', 'incorrect_time_in', 'incorrect_time_out', 'missing_record', 'camera_gps_problem', 'other'])], 'requested_date' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'], 'requested_time_in_at' => ['nullable', 'date_format:Y-m-d\TH:i'], 'requested_time_out_at' => ['nullable', 'date_format:Y-m-d\TH:i'], 'reason' => ['required', 'string', 'min:10', 'max:2000'], 'employee_id' => ['prohibited'], 'status' => ['prohibited'], 'reviewed_by_user_id' => ['prohibited']];
    }
}
