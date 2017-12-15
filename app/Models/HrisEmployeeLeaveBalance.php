<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class HrisEmployeeLeaveBalance extends Model
{
    use HasFactory;

    protected $table = 'hris_employee_leave_balances';

    protected $fillable = [
        'tenant_id',
        'employee_id',
        'leave_type_id',
        'year',
        'quota',
        'used',
        'remaining',
    ];

    protected $casts = [
        'year' => 'integer',
        'quota' => 'integer',
        'used' => 'integer',
        'remaining' => 'integer',
    ];

    public function employee()
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }

    public function leaveType()
    {
        return $this->belongsTo(HrisLeaveType::class, 'leave_type_id');
    }
}
