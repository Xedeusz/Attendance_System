<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class HolidayImport extends Model
{
    use HasFactory;

    protected $fillable = ['approved_by', 'year', 'source_url', 'declaration', 'content_hash', 'imported_count'];
}
