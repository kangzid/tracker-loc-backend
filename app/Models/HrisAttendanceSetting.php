<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class HrisAttendanceSetting extends Model
{
    use HasFactory;

    protected $table = 'hris_attendance_settings';

    protected $fillable = [
        'tenant_id',
        'is_shift_enabled',
        'standard_working_days',
        'check_in_start',
        'work_start_time',
        'late_tolerance_time',
        'check_in_end',
        'lock_after_late_cutoff',
        'late_cutoff_policy',
        'work_end_time',
        'min_checkout_at_work_end',
        'require_geofence_checkout',
        'edit_delete_limit_days',
        'allow_admin_bypass',
    ];

    protected $casts = [
        'is_shift_enabled' => 'boolean',
        'standard_working_days' => 'array',
        'lock_after_late_cutoff' => 'boolean',
        'min_checkout_at_work_end' => 'boolean',
        'require_geofence_checkout' => 'boolean',
        'edit_delete_limit_days' => 'integer',
        'allow_admin_bypass' => 'boolean',
    ];

    public function tenant()
    {
        return $this->belongsTo(User::class, 'tenant_id');
    }
}
