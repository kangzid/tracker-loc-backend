<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class HrisContractType extends Model
{
    use HasFactory;

    protected $table = 'hris_contract_types';

    protected $fillable = [
        'tenant_id',
        'code',
        'name',
        'is_permanent',
        'description',
        'is_active',
    ];

    protected $casts = [
        'is_permanent' => 'boolean',
        'is_active' => 'boolean',
    ];
}
