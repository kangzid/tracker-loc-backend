<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class HrisOvertimeSetting extends Model
{
    use HasFactory;

    protected $table = 'hris_overtime_settings';

    protected $fillable = [
        'tenant_id',
        'default_rate_per_hour',
        'calculation_type',
        'min_duration_minutes',
        'auto_approve',
    ];

    protected $casts = [
        'default_rate_per_hour' => 'decimal:2',
        'min_duration_minutes' => 'integer',
        'auto_approve' => 'boolean',
    ];

    public function tenant()
    {
        return $this->belongsTo(User::class, 'tenant_id');
    }
}
