<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LeaveType extends Model
{
    use HasFactory;

    protected $fillable = ['code', 'name', 'description', 'requires_document', 'affects_balance', 'allows_half_day', 'is_active', 'duration_policy'];

    protected function casts(): array
    {
        return ['requires_document' => 'boolean', 'affects_balance' => 'boolean', 'allows_half_day' => 'boolean', 'is_active' => 'boolean'];
    }

    public function requests(): HasMany
    {
        return $this->hasMany(LeaveRequest::class);
    }
}
