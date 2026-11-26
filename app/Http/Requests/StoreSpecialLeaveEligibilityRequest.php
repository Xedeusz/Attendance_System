<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSpecialLeaveEligibilityRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $eligibilityRequest = $this->route('eligibilityRequest');

        return $this->user()?->canAuthenticate() && (! $eligibilityRequest || $this->user()->can('update', $eligibilityRequest));
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $eligibilityRequest = $this->route('eligibilityRequest');

        return [
            'leave_type_id' => [$eligibilityRequest ? 'prohibited' : 'required', 'integer', Rule::exists('leave_types', 'id')->where(fn ($query) => $query->whereIn('code', ['MATERNITY', 'PATERNITY'])->where('is_active', true))],
            'event_category' => ['required', Rule::in(['expected_live_childbirth', 'actual_live_childbirth', 'live_childbirth_solo_parent', 'miscarriage_or_emergency_termination', 'paternity_qualifying_event', 'other_for_hr_review'])],
            'expected_event_date' => ['nullable', 'date_format:Y-m-d', 'required_without:actual_event_date'],
            'actual_event_date' => ['nullable', 'date_format:Y-m-d', 'required_without:expected_event_date'],
            'additional_information' => ['required', 'string', 'min:10', 'max:3000'],
            'supporting_documents' => ['nullable', 'array', 'max:5'],
            'supporting_documents.*' => ['file', 'mimes:pdf,jpg,jpeg,png', 'extensions:pdf,jpg,jpeg,png', 'max:'.config('leave.document_max_kilobytes')],
            'employee_declaration' => ['accepted'],
            'status' => ['prohibited'], 'employee_id' => ['prohibited'], 'entitlement_days' => ['prohibited'],
        ];
    }
}
