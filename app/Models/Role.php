<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Role extends Model
{
    public const HR = 1;

    public const EMPLOYEE = 2;

    public $timestamps = false;

    protected $fillable = ['id', 'code'];
}
