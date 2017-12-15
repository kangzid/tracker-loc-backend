<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Employee extends Model
{
    use HasFactory;

    protected $fillable = [
        'admin_id',
        'user_id',
        'employee_id',
        'phone',
        'address',
        'department',
        'position',
        'latitude',
        'longitude',
        'last_location_update',
        'basic_salary',
        'photo_base64',
    ];

    protected static function boot()
    {
        parent::boot();

        // Auto-delete all location history when employee is deleted
        static::deleting(function ($employee) {
            $employee->locations()->delete();
        });
    }

    protected function casts(): array
    {
        return [
            'latitude' => 'decimal:8',
            'longitude' => 'decimal:8',
            'last_location_update' => 'datetime',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function admin()
    {
        return $this->belongsTo(User::class, 'admin_id');
    }

    public function payslips()
    {
        return $this->hasMany(HrisPayslip::class, 'employee_id');
    }

    public function attendances()
    {
        return $this->hasMany(Attendance::class);
    }

    public function tasks()
    {
        return $this->hasMany(Task::class, 'assigned_to');
    }

    public function locations()
    {
        return $this->morphMany(Location::class, 'trackable');
    }

    public function latestLocation()
    {
        return $this->morphOne(Location::class, 'trackable')->latest('recorded_at');
    }
}
