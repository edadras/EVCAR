<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CachedRfidCard extends Model
{
    protected $table = 'cached_rfid_cards';

    protected $fillable = [
        'uuid', 'cloud_rfid_uuid', 'cached_user_uuid', 'id_tag', 'status',
        'expires_at', 'cloud_version', 'synced_at',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'synced_at' => 'datetime',
        ];
    }
}
