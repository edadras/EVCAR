<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PanelCommandLog extends Model
{
    protected $fillable = [
        'uuid', 'source', 'cloud_command_uuid', 'user_id', 'connector_id', 'command_type', 'payload',
        'status', 'result', 'error', 'simulated',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'result' => 'array',
            'simulated' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
