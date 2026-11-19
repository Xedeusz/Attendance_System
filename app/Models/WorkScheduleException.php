<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkScheduleException extends Model
{
    use HasFactory;

    public const KINDS = ['excused' => 'Excused day', 'shift' => 'Approved schedule override / holiday shift', 'early_departure' => 'Approved early departure'];

    protected $fillable = ['employee_id', 'work_schedule_id', 'created_by', 'work_date', 'kind', 'adjusted_end', 'reason', 'active'];

    protected function casts(): array
    {
        return ['work_date' => 'date', 'active' => 'boolean'];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function workSchedule(): BelongsTo
    {
        return $this->belongsTo(WorkSchedule::class);
    }
}
