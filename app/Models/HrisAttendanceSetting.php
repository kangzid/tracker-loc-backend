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
        'edit_delete_limit_days',
        'allow_admin_bypass',
    ];

    protected $casts = [
        'edit_delete_limit_days' => 'integer',
        'allow_admin_bypass' => 'boolean',
    ];

    public function tenant()
    {
        return $this->belongsTo(User::class, 'tenant_id');
    }
}
