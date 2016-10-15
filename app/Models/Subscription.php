<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Subscription extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'plan',
        'max_employees',
        'max_vehicles',
        'company_name',
        'contact_phone',
        'started_at',
        'expired_at',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'expired_at' => 'datetime',
        ];
    }

    /** Relasi ke User (si Admin pemilik subscription) */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /** Cek apakah subscription masih aktif dan belum expired */
    public function isActive(): bool
    {
        return $this->status === 'active' && $this->expired_at->isFuture();
    }

    /** Sisa hari sebelum expired */
    public function daysRemaining(): int
    {
        return max(0, now()->diffInDays($this->expired_at, false));
    }
}
