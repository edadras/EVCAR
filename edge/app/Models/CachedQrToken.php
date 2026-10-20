<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CachedQrToken extends Model
{
    protected $table = 'cached_qr_tokens';

    protected $fillable = [
        'uuid', 'cloud_qr_uuid', 'token', 'cloud_charger_uuid', 'cloud_connector_uuid',
        'connector_number', 'status', 'offline_capable', 'expires_at', 'cloud_version', 'synced_at',
    ];

    protected function casts(): array
    {
        return [
            'offline_capable' => 'boolean',
            'expires_at' => 'datetime',
            'synced_at' => 'datetime',
        ];
    }
}
