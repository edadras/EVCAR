<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ConnectorAuthGrant extends Model
{
    protected $fillable = [
        'cached_user_uuid', 'connector_id', 'grant_type',
        'source_uuid', 'granted_at', 'expires_at', 'meta',
    ];

    protected function casts(): array
    {
        return [
            'granted_at' => 'datetime',
            'expires_at' => 'datetime',
            'meta' => 'array',
        ];
    }

    public function connector(): BelongsTo
    {
        return $this->belongsTo(EdgeConnector::class, 'connector_id');
    }
}
