<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class HrisBank extends Model
{
    use HasFactory;

    protected $table = 'hris_banks';
    protected $fillable = ['tenant_id', 'name', 'code', 'is_active'];
    protected $casts = ['is_active' => 'boolean'];
}
