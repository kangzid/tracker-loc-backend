<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Location extends Model
{
    use HasFactory;

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

    public function trackable()
    {
        return $this->morphTo();
    }
}