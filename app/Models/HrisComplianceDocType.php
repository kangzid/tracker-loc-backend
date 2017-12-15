<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class HrisComplianceDocType extends Model
{
    use HasFactory;

    protected $table = 'hris_compliance_doc_types';
    protected $fillable = ['tenant_id', 'target_type', 'name', 'code', 'default_reminder_days', 'description', 'is_active'];
    protected $casts = [
        'is_active' => 'boolean',
        'default_reminder_days' => 'integer',
    ];
}
