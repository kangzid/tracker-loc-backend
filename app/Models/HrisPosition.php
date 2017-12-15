<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class HrisPosition extends Model
{
    use HasFactory;

    protected $table = 'hris_positions';

    protected $fillable = [
        'tenant_id',
        'department_id',
        'name',
        'code',
        'level',
        'description',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function department()
    {
        return $this->belongsTo(HrisDepartment::class, 'department_id');
    }

    public function employees()
    {
        return $this->hasMany(Employee::class, 'position', 'name');
    }
}
