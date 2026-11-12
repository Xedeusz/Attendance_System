<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LocalMailMessage extends Model
{
    public $timestamps = false;

    protected $fillable = ['recipient_hash', 'payload', 'expires_at'];

    protected $hidden = ['payload'];

    protected function casts(): array
    {
        return ['payload' => 'encrypted:array', 'created_at' => 'datetime', 'expires_at' => 'datetime'];
    }
}
