<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StationIdentity extends Model
{
    protected $table = 'station_identity';

    protected $fillable = [
        'uuid', 'cloud_station_uuid', 'edge_code', 'name',
        'cloud_api_url', 'cloud_sync_token', 'local_hostname',
        'wifi_ssid', 'timezone', 'offline_mode_enabled',
        'software_version', 'last_cloud_heartbeat_at', 'last_cloud_sync_at', 'meta',
    ];

    protected function casts(): array
    {
        return [
            'offline_mode_enabled' => 'boolean',
            'last_cloud_heartbeat_at' => 'datetime',
            'last_cloud_sync_at' => 'datetime',
            'meta' => 'array',
        ];
    }
}
