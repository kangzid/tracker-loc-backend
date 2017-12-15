<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class HrisViolationType extends Model
{
    use HasFactory;

    protected $table = 'hris_violation_types';

    protected $fillable = [
        'tenant_id',
        'code',
        'name',
        'default_duration_months',
        'description',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'default_duration_months' => 'integer',
    ];

    public function tenant()
    {
        return $this->belongsTo(User::class, 'tenant_id');
    }
}
