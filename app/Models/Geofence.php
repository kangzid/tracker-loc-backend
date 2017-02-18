<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Geofence extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'center_lat',
        'center_lng',
        'radius',
        'type',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'center_lat' => 'decimal:8',
            'center_lng' => 'decimal:8',
            'is_active' => 'boolean',
        ];
    }

    public function isInsideGeofence($latitude, $longitude)
    {
        $distance = $this->calculateDistance($latitude, $longitude, $this->center_lat, $this->center_lng);
        return $distance <= $this->radius;
    }

    private function calculateDistance($lat1, $lon1, $lat2, $lon2)
    {
        $earthRadius = 6371000; // meters
        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);
        $a = sin($dLat/2) * sin($dLat/2) + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLon/2) * sin($dLon/2);
        $c = 2 * atan2(sqrt($a), sqrt(1-$a));
        return $earthRadius * $c;
    }
}