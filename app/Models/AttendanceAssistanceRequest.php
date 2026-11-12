<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AttendanceAssistanceRequest extends Model
{
    protected $fillable = ['employee_id', 'action', 'reason', 'status', 'reviewer_user_id', 'remarks', 'reviewed_at'];

    protected function casts(): array
    {
        return ['reason' => 'encrypted', 'reviewed_at' => 'datetime'];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
