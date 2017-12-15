<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class HrisRequest extends Model
{
    use HasFactory;

    protected $table = 'hris_requests';

    protected $fillable = [
        'tenant_id',
        'employee_id',
        'request_type',
        'code',
        'leave_type_id',
        'start_date',
        'end_date',
        'days_count',
        'reason',
        'attachment_base64',
        'attachment_name',
        'from_shift',
        'to_shift',
        'status',
        'approver_note',
        'approved_by',
        'approved_at',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'days_count' => 'integer',
        'approved_at' => 'datetime',
    ];

    public function employee()
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }

    public function leaveType()
    {
        return $this->belongsTo(HrisLeaveType::class, 'leave_type_id');
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
