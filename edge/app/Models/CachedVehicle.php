<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CachedVehicle extends Model
{
    protected $fillable = [
        'uuid', 'cloud_vehicle_uuid', 'cached_user_uuid', 'plate_normalized',
        'plate_two', 'plate_letter', 'plate_three', 'plate_province',
        'brand', 'model', 'battery_kwh', 'is_default', 'cloud_version', 'synced_at',
    ];

    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
            'battery_kwh' => 'decimal:1',
            'synced_at' => 'datetime',
        ];
    }
}
