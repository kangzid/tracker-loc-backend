<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Notification extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * Prepare a date for array / JSON serialization.
     */
    protected function serializeDate(\DateTimeInterface $date)
    {
        return $date->format('Y-m-d H:i:s');
    }

    protected $fillable = [
        'admin_id',
        'employee_id',
        'created_by',
        'title',
        'message',
        'image_url',
        'type',
        'recipient_type',
        'admin_ids',
        'data',
        'is_read',
    ];

    protected function casts(): array
    {
        return [
            'data' => 'array',
            'admin_ids' => 'array',
            'is_read' => 'boolean',
        ];
    }

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function adminStatus()
    {
        return $this->hasMany(AdminNotificationStatus::class);
    }

    public function admin()
    {
        return $this->belongsTo(User::class, 'admin_id');
    }
}