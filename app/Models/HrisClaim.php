<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class HrisClaim extends Model
{
    use HasFactory;

    protected $table = 'hris_claims';

    protected $fillable = [
        'tenant_id',
        'employee_id',
        'claim_type_id',
        'code',
        'title',
        'claim_date',
        'amount',
        'description',
        'receipt_path',
        'receipt_name',
        'status',
        'approved_by',
        'approver_note',
        'approved_at',
        'paid_at',
        'paid_via',
        'payroll_id',
    ];

    protected $casts = [
        'claim_date' => 'date',
        'amount' => 'decimal:2',
        'approved_at' => 'datetime',
        'paid_at' => 'datetime',
    ];

    public function employee()
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }

    public function claimType()
    {
        return $this->belongsTo(HrisClaimType::class, 'claim_type_id');
    }

    public function tenant()
    {
        return $this->belongsTo(User::class, 'tenant_id');
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function payroll()
    {
        return $this->belongsTo(HrisPayroll::class, 'payroll_id');
    }
}
