<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CachedTariff extends Model
{
    protected $fillable = [
        'uuid', 'cloud_tariff_uuid', 'name', 'pricing_model',
        'price_per_kwh', 'price_per_minute', 'session_fee', 'rules',
        'cloud_version', 'synced_at', 'is_default', 'station_assigned', 'meta',
    ];

    protected function casts(): array
    {
        return [
            'price_per_kwh' => 'decimal:4',
            'price_per_minute' => 'decimal:4',
            'session_fee' => 'decimal:2',
            'rules' => 'array',
            'meta' => 'array',
            'synced_at' => 'datetime',
            'is_default' => 'boolean',
            'station_assigned' => 'boolean',
        ];
    }
}
