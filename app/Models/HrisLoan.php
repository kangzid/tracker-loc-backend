<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class HrisLoan extends Model
{
    use HasFactory;

    protected $table = 'hris_loans';

    protected $fillable = [
        'tenant_id',
        'employee_id',
        'code',
        'amount',
        'tenor_months',
        'monthly_deduction',
        'paid_amount',
        'remaining_amount',
        'status',
        'reason',
        'disbursed_at',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'monthly_deduction' => 'decimal:2',
        'paid_amount' => 'decimal:2',
        'remaining_amount' => 'decimal:2',
        'disbursed_at' => 'date',
        'tenor_months' => 'integer',
    ];

    public function employee()
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }

    public function tenant()
    {
        return $this->belongsTo(User::class, 'tenant_id');
    }

    public function payments()
    {
        return $this->hasMany(HrisLoanPayment::class, 'loan_id');
    }
}
