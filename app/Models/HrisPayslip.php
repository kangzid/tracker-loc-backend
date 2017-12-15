<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class HrisPayslip extends Model
{
    protected $fillable = [
        'payroll_id',
        'employee_id',
        'basic_salary',
        'allowances',
        'overtime_pay',
        'reimbursements',
        'loan_deductions',
        'absence_deductions',
        'tax_deductions',
        'bpjs_kesehatan',
        'bpjs_ketenagakerjaan',
        'adjustments_addition',
        'adjustments_deduction',
        'net_salary',
        'status',
        'notes'
    ];

    protected $casts = [
        'basic_salary' => 'decimal:2',
        'allowances' => 'decimal:2',
        'overtime_pay' => 'decimal:2',
        'reimbursements' => 'decimal:2',
        'loan_deductions' => 'decimal:2',
        'absence_deductions' => 'decimal:2',
        'tax_deductions' => 'decimal:2',
        'bpjs_kesehatan' => 'decimal:2',
        'bpjs_ketenagakerjaan' => 'decimal:2',
        'adjustments_addition' => 'decimal:2',
        'adjustments_deduction' => 'decimal:2',
        'net_salary' => 'decimal:2',
    ];

    public function payroll()
    {
        return $this->belongsTo(HrisPayroll::class, 'payroll_id');
    }

    public function employee()
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }
}
