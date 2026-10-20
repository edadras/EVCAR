<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SyncInbox extends Model
{
    protected $table = 'sync_inbox';

    protected $fillable = [
        'event_uuid', 'edge_station_uuid', 'event_type', 'aggregate_type', 'aggregate_uuid',
        'payload', 'occurred_at', 'received_at', 'status', 'attempts', 'last_error', 'processed_at',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'occurred_at' => 'datetime',
            'received_at' => 'datetime',
            'processed_at' => 'datetime',
        ];
    }
}
