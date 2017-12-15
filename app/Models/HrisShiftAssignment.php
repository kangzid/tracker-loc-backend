<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class HrisShiftAssignment extends Model
{
    use HasFactory;

    protected $table = 'hris_shift_assignments';

    protected $fillable = [
        'tenant_id',
        'employee_id',
        'shift_id',
        'date',
        'notes',
    ];

    protected $casts = [
        'date' => 'date:Y-m-d',
    ];

    public function tenant()
    {
        return $this->belongsTo(User::class, 'tenant_id');
    }

    public function employee()
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }

    public function shift()
    {
        return $this->belongsTo(HrisShift::class, 'shift_id');
    }
}
