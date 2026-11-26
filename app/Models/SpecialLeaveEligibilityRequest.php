<?php

namespace App\Models;

use Database\Factories\SpecialLeaveEligibilityRequestFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class SpecialLeaveEligibilityRequest extends Model
{
    /** @use HasFactory<SpecialLeaveEligibilityRequestFactory> */
    use HasFactory;

    public const STATUSES = ['pending_hr_review', 'additional_information_required', 'verified', 'not_eligible', 'withdrawn'];

    protected $fillable = ['employee_id', 'leave_type_id', 'reference', 'event_category', 'expected_event_date', 'actual_event_date', 'status', 'additional_information', 'employee_declaration', 'submitted_at', 'reviewed_by_user_id', 'reviewed_at', 'hr_remarks', 'last_resubmitted_at'];

    protected $hidden = ['additional_information', 'hr_remarks'];

    protected function casts(): array
    {
        return ['expected_event_date' => 'date', 'actual_event_date' => 'date', 'employee_declaration' => 'boolean', 'submitted_at' => 'datetime', 'reviewed_at' => 'datetime', 'last_resubmitted_at' => 'datetime', 'additional_information' => 'encrypted', 'hr_remarks' => 'encrypted'];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function type(): BelongsTo
    {
        return $this->belongsTo(LeaveType::class, 'leave_type_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by_user_id');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(SpecialLeaveEligibilityDocument::class);
    }

    public function activities(): HasMany
    {
        return $this->hasMany(SpecialLeaveEligibilityActivity::class)->orderBy('created_at')->orderBy('id');
    }

    public function entitlement(): HasOne
    {
        return $this->hasOne(LeaveEntitlement::class);
    }
}
