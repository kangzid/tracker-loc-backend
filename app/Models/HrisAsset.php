<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HrisAsset extends Model
{
    protected $fillable = [
        'tenant_id',
        'asset_code',
        'name',
        'category',
        'serial_number',
        'employee_id',
        'status',
        'handover_date',
        'return_date',
        'condition',
        'notes',
    ];

    protected $casts = [
        'handover_date' => 'date',
        'return_date' => 'date',
    ];

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }
}
