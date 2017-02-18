<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Vehicle extends Model
{
    use HasFactory;

    protected $fillable = [
        'vehicle_number',
        'vehicle_type',
        'brand',
        'model',
        'year',
        'latitude',
        'longitude',
        'last_location_update',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'latitude' => 'decimal:8',
            'longitude' => 'decimal:8',
            'last_location_update' => 'datetime',
            'is_active' => 'boolean',
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
}