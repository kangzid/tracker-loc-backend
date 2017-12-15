<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class HrisLeaveType extends Model
{
    use HasFactory;

    protected $table = 'hris_leave_types';

    protected $fillable = [
        'tenant_id',
        'code',
        'name',
        'default_days',
        'is_paid',
        'requires_attachment',
        'description',
    ];

    protected $casts = [
        'default_days' => 'integer',
        'is_paid' => 'boolean',
        'requires_attachment' => 'boolean',
    ];

    public function tenant()
    {
        return $this->belongsTo(User::class, 'tenant_id');
    }
}
