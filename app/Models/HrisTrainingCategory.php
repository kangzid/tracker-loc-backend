<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class HrisTrainingCategory extends Model
{
    use HasFactory;

    protected $table = 'hris_training_categories';
    protected $fillable = ['tenant_id', 'name', 'code', 'description', 'is_active'];
    protected $casts = ['is_active' => 'boolean'];
}
