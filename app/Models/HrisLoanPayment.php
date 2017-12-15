<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class HrisLoanPayment extends Model
{
    use HasFactory;

    protected $table = 'hris_loan_payments';

    protected $fillable = [
        'loan_id',
        'amount',
        'payment_date',
        'payment_method',
        'payroll_id',
        'notes',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'payment_date' => 'date',
    ];

    public function loan()
    {
        return $this->belongsTo(HrisLoan::class, 'loan_id');
    }

    public function payroll()
    {
        return $this->belongsTo(HrisPayroll::class, 'payroll_id');
    }
}
