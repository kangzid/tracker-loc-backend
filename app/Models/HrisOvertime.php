<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class HrisOvertime extends Model
{
    use HasFactory;

    protected $table = 'hris_overtimes';

    protected $fillable = [
        'tenant_id',
        'employee_id',
        'date',
        'start_time',
        'end_time',
        'duration_hours',
        'reason',
        'rate_per_hour',
        'total_pay',
        'status',
        'approved_by',
        'approver_note',
        'approved_at',
    ];

    protected $casts = [
        'date' => 'date',
        'duration_hours' => 'decimal:2',
        'rate_per_hour' => 'decimal:2',
        'total_pay' => 'decimal:2',
        'approved_at' => 'datetime',
    ];

    public function employee()
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }

    public function tenant()
    {
        return $this->belongsTo(User::class, 'tenant_id');
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
