<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Employee extends Model
{
    use HasFactory;

    protected $fillable = ['employee_number', 'first_name', 'middle_name', 'last_name', 'suffix', 'sex', 'contact_number', 'department_id', 'position_title', 'date_hired', 'employment_status'];

    protected function casts(): array
    {
        return ['date_hired' => 'date'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(EmployeeBranchAssignment::class);
    }

    public function currentAssignment(): HasOne
    {
        return $this->hasOne(EmployeeBranchAssignment::class)->whereNull('effective_until')->where('is_primary', true);
    }

    public function getFullNameAttribute(): string
    {
        return trim(implode(' ', array_filter([$this->first_name, $this->middle_name, $this->last_name, $this->suffix])));
    }

    public function getWorkEmailAttribute(): string
    {
        return $this->user->email;
    }
}
