<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SyncCursor extends Model
{
    protected $table = 'sync_cursors';

    protected $fillable = [
        'stream', 'cursor_position', 'last_synced_at',
        'consecutive_failures', 'next_retry_at', 'last_error',
    ];

    protected function casts(): array
    {
        return [
            'last_synced_at' => 'datetime',
            'next_retry_at' => 'datetime',
        ];
    }
}
