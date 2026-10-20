<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EdgeCharger extends Model
{
    protected $table = 'chargers';

    protected $fillable = [
        'uuid', 'cloud_charger_uuid', 'charge_point_id', 'max_power_kw', 'is_online', 'status', 'meta',
        'vendor', 'model', 'serial_number', 'firmware_version', 'last_heartbeat_at',
        'last_boot_at', 'connected_at', 'remote_address', 'ocpp_status', 'error_code',
    ];

    protected function casts(): array
    {
        return [
            'is_online' => 'boolean',
            'meta' => 'array',
            'last_heartbeat_at' => 'datetime',
            'last_boot_at' => 'datetime',
            'connected_at' => 'datetime',
        ];
    }

    public function connectors(): HasMany
    {
        return $this->hasMany(EdgeConnector::class, 'charger_id');
    }
}
