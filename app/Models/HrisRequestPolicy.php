<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class HrisRequestPolicy extends Model
{
    use HasFactory;

    protected $table = 'hris_request_policies';

    protected $fillable = [
        'tenant_id',
        'policy_type',
        'max_days_per_year',
        'requires_attachment',
        'is_paid',
        'description',
    ];

    protected $casts = [
        'max_days_per_year' => 'integer',
        'requires_attachment' => 'boolean',
        'is_paid' => 'boolean',
    ];
}
