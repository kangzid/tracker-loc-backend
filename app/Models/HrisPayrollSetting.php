<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class HrisPayrollSetting extends Model
{
    use HasFactory;

    protected $table = 'hris_payroll_settings';

    protected $fillable = [
        'tenant_id',
        'working_days_divider_type', // fixed_25, fixed_22, fixed_21, fixed_20, calendar_days, custom
        'custom_working_days',       // used if working_days_divider_type is custom
        'overtime_rate_multiplier',  // optional
        'late_deduction_rate',       // optional
        'auto_generate_payslip',     // boolean
    ];

    protected $casts = [
        'custom_working_days' => 'integer',
        'overtime_rate_multiplier' => 'float',
        'late_deduction_rate' => 'float',
        'auto_generate_payslip' => 'boolean',
    ];

    public function tenant()
    {
        return $this->belongsTo(User::class, 'tenant_id');
    }
}
