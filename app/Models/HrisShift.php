<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class HrisShift extends Model
{
    use HasFactory;

    protected $table = 'hris_shifts';

    protected $fillable = [
        'tenant_id',
        'name',
        'code',
        'check_in_start',
        'work_start_time',
        'late_tolerance_time',
        'check_in_end',
        'work_end_time',
        'is_night_shift',
        'color',
        'is_active',
    ];

    protected $casts = [
        'is_night_shift' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function tenant()
    {
        return $this->belongsTo(User::class, 'tenant_id');
    }

    public function assignments()
    {
        return $this->hasMany(HrisShiftAssignment::class, 'shift_id');
    }

    public function attendances()
    {
        return $this->hasMany(Attendance::class, 'shift_id');
    }
}
