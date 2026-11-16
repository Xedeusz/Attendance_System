<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class LeaveApplicationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->canAuthenticate();
    }

    public function rules(): array
    {
        return [
            'leave_type_id' => ['required', 'integer', 'exists:leave_types,id'], 'start_date' => ['required', 'date_format:Y-m-d'], 'end_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:start_date'],
            'requested_duration' => ['required', 'numeric', 'min:0.5', 'max:366', 'multiple_of:0.5'], 'half_day' => ['nullable', Rule::in(['am', 'pm'])],
            'reason' => ['required', 'string', 'min:10', 'max:2000'], 'supporting_document' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'extensions:pdf,jpg,jpeg,png', 'max:'.config('leave.document_max_kilobytes')],
            'employee_id' => ['prohibited'], 'status' => ['prohibited'], 'assigned_supervisor_user_id' => ['prohibited'], 'charged_duration' => ['prohibited']];
    }
}
