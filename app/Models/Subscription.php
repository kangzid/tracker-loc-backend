<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Subscription extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected $appends = ['plan'];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'expired_at' => 'datetime',
            'ai_credits_limit' => 'integer',
            'ai_credits_used' => 'integer',
        ];
    }

    public function getPlanAttribute()
    {
        return $this->plan_name_from_rel ?? 'trial';
    }

    public function getPlanNameFromRelAttribute()
    {
        return $this->planDetails?->slug;
    }

    /** Relasi ke User (si Admin pemilik subscription) */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /** Relasi ke paket yang dipilih */
    public function planDetails()
    {
        return $this->belongsTo(Plan::class, 'plan_id');
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

    /** Sisa kredit AI */
    public function aiCreditsRemaining(): int
    {
        return max(0, $this->ai_credits_limit - $this->ai_credits_used);
    }
}
