<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AdminNotificationStatus extends Model
{
    protected $table = 'admin_notification_status';

    protected $fillable = [
        'admin_id',
        'notification_id',
        'read_at',
        'deleted_by_admin_at',
    ];

    protected $casts = [
        'read_at' => 'datetime',
        'deleted_by_admin_at' => 'datetime',
    ];

    public function admin()
    {
        return $this->belongsTo(User::class, 'admin_id');
    }

    public function notification()
    {
        return $this->belongsTo(Notification::class);
    }
}
