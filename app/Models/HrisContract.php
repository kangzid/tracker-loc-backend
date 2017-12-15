<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class HrisContract extends Model
{
    use HasFactory;

    protected $table = 'hris_contracts';

    protected $fillable = [
        'tenant_id',
        'employee_id',
        'contract_number',
        'document_number',
        'contract_type',
        'contract_date',
        'start_date',
        'end_date',
        'department',
        'position',
        'basic_salary',
        'allowance_extrafooding',
        'allowance_fuel',
        'allowance_communication',
        'bank_name',
        'bank_account_number',
        'bank_account_holder',
        'allowances_json',
        'document_pdf_path',
        'document_pdf_name',
        'document_pdf_base64',
        'status',
        'notes',
    ];

    protected $casts = [
        'contract_date' => 'date:Y-m-d',
        'start_date' => 'date:Y-m-d',
        'end_date' => 'date:Y-m-d',
        'basic_salary' => 'decimal:2',
        'allowance_extrafooding' => 'decimal:2',
        'allowance_fuel' => 'decimal:2',
        'allowance_communication' => 'decimal:2',
        'allowances_json' => 'array',
    ];

    public function employee()
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }
}
