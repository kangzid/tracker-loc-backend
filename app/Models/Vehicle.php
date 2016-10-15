<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Vehicle extends Model
{
    use HasFactory;

    protected $fillable = [
        'admin_id',
        'vehicle_number',
        'vehicle_type',
        'brand',
        'model',
        'year',
        'latitude',
        'longitude',
        'last_location_update',
        'is_active',
        'tracking_token',
        'token_generated_at',
    ];

    protected function casts(): array
    {
        return [
            'latitude' => 'decimal:8',
            'longitude' => 'decimal:8',
            'last_location_update' => 'datetime',
            'is_active' => 'boolean',
            'token_generated_at' => 'datetime',
        ];
    }

    public function locations()
    {
        return $this->morphMany(Location::class, 'trackable');
    }

    public function latestLocation()
    {
        return $this->morphOne(Location::class, 'trackable')->latest('recorded_at');
    }

    public function admin()
    {
        return $this->belongsTo(User::class, 'admin_id');
    }

    /**
     * Generate unique tracking token for GPS device
     */
    public function generateTrackingToken(): string
    {
        $token = bin2hex(random_bytes(32)); // 64 character token
        $this->update([
            'tracking_token' => $token,
            'token_generated_at' => now(),
        ]);
        return $token;
    }

    /**
     * Regenerate tracking token (revoke old token)
     */
    public function regenerateTrackingToken(): string
    {
        return $this->generateTrackingToken();
    }
}