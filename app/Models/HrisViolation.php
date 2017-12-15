<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class HrisViolation extends Model
{
    use HasFactory;

    protected $table = 'hris_violations';

    protected $fillable = [
        'tenant_id',
        'employee_id',
        'violation_date',
        'valid_from',
        'valid_until',
        'violation_type',
        'document_number',
        'contract_number',
        'description',
        'violation_points',
        'legal_basis',
        'evidence_base64',
        'status',
        'issued_by',
        'notes',
    ];

    protected $casts = [
        'violation_date' => 'date',
        'valid_from' => 'date',
        'valid_until' => 'date',
    ];

    public function employee()
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }

    public function issuer()
    {
        return $this->belongsTo(User::class, 'issued_by');
    }

    public function tenant()
    {
        return $this->belongsTo(User::class, 'tenant_id');
    }
}
