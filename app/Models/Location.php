<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

class Location extends Model
{
    use HasFactory;

    /**
     * Prepare a date for array / JSON serialization.
     */
    protected function serializeDate(\DateTimeInterface $date)
    {
        return $date->format('Y-m-d H:i:s');
    }

    protected $fillable = [
        'trackable_type',
        'trackable_id',
        'latitude',
        'longitude',
        'address',
        'speed',
        'accuracy',
        'recorded_at',
    ];

    protected function casts(): array
    {
        return [
            'latitude' => 'decimal:8',
            'longitude' => 'decimal:8',
            'speed' => 'decimal:2',
            'accuracy' => 'decimal:2',
            'recorded_at' => 'datetime',
        ];
    }

    // OPTIMIZATION: Query Scopes
    public function scopeForEmployee(Builder $query, int $employeeId): Builder
    {
        return $query->where('trackable_type', Employee::class)
                     ->where('trackable_id', $employeeId);
    }

    public function scopeForVehicle(Builder $query, int $vehicleId): Builder
    {
        return $query->where('trackable_type', Vehicle::class)
                     ->where('trackable_id', $vehicleId);
    }

    public function scopeRecent(Builder $query, int $minutes = 30): Builder
    {
        return $query->where('recorded_at', '>=', now()->subMinutes($minutes));
    }

    public function scopeToday(Builder $query): Builder
    {
        return $query->whereDate('recorded_at', today());
    }

    public function trackable()
    {
        return $this->morphTo();
    }
}