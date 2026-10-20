<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SyncActivityLog extends Model
{
    protected $fillable = [
        'action', 'status', 'stats', 'message', 'error',
    ];

    protected function casts(): array
    {
        return [
            'stats' => 'array',
        ];
    }
}
