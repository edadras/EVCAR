<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SyncOutbox extends Model
{
    protected $table = 'sync_outbox';

    protected $fillable = [
        'event_uuid', 'event_type', 'aggregate_type', 'aggregate_uuid',
        'payload', 'occurred_at', 'status', 'attempts', 'next_retry_at',
        'last_error', 'acked_at',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'occurred_at' => 'datetime',
            'next_retry_at' => 'datetime',
            'acked_at' => 'datetime',
        ];
    }
}
