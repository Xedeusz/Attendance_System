<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SupervisorSignature extends Model
{
    use HasFactory;

    protected $fillable = ['supervisor_user_id', 'storage_path', 'version', 'registered_at', 'revoked_at'];

    protected $hidden = ['storage_path'];

    protected function casts(): array
    {
        return ['registered_at' => 'datetime', 'revoked_at' => 'datetime'];
    }

    public function scopeActiveFor(Builder $query, User $supervisor): Builder
    {
        return $query->where('supervisor_user_id', $supervisor->id)->whereNull('revoked_at');
    }
}
