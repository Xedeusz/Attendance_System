<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Branch extends Model
{
    use HasFactory;

    protected $fillable = ['branch_code', 'branch_name', 'address', 'city', 'province', 'postal_code', 'latitude', 'longitude', 'geofence_radius_meters', 'status'];

    public function assignments(): HasMany
    {
        return $this->hasMany(EmployeeBranchAssignment::class);
    }

    public function currentAssignments(): HasMany
    {
        return $this->assignments()->whereNull('effective_until')->where('is_primary', true);
    }
}
