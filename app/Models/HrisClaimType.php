<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class HrisClaimType extends Model
{
    use HasFactory;

    protected $table = 'hris_claim_types';

    protected $fillable = [
        'tenant_id',
        'code',
        'name',
        'description',
        'max_amount_per_claim',
        'max_amount_per_month',
        'requires_receipt',
        'is_active',
    ];

    protected $casts = [
        'max_amount_per_claim' => 'decimal:2',
        'max_amount_per_month' => 'decimal:2',
        'requires_receipt' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function claims()
    {
        return $this->hasMany(HrisClaim::class, 'claim_type_id');
    }

    public function tenant()
    {
        return $this->belongsTo(User::class, 'tenant_id');
    }
}
