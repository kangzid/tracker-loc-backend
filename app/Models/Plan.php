<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Plan extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'price_monthly',
        'max_employees',
        'max_vehicles',
        'ai_credits',
        'features',
        'is_active',
        'is_custom',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'features' => 'array',
            'is_active' => 'boolean',
            'is_custom' => 'boolean',
        ];
    }

    public function subscriptions()
    {
        return $this->hasMany(Subscription::class);
    }

    /**
     * Format harga ke Rupiah
     */
    public function formattedPrice(): string
    {
        if ($this->is_custom) return 'Custom';
        return 'Rp ' . number_format($this->price_monthly, 0, ',', '.');
    }
}
