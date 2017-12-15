<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class HrisPayroll extends Model
{
    protected $fillable = [
        'tenant_id',
        'payroll_type',
        'code',
        'batch_name',
        'month',
        'year',
        'slip_date',
        'period_start',
        'period_end',
        'total_amount',
        'status',
        'processed_by'
    ];

    protected $casts = [
        'period_start' => 'date',
        'period_end' => 'date',
        'slip_date' => 'date',
        'total_amount' => 'decimal:2',
    ];

    public function payslips()
    {
        return $this->hasMany(HrisPayslip::class, 'payroll_id');
    }

    public function processor()
    {
        return $this->belongsTo(User::class, 'processed_by');
    }
}
