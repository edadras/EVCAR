<?php

namespace App\Models;

use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EdgeStation extends Model
{
    use HasUuid;

    protected $fillable = [
        'uuid', 'station_id', 'edge_code', 'api_token_hash',
        'software_version', 'hostname', 'public_ip', 'sync_status',
        'last_heartbeat_at', 'last_sync_at', 'last_sync_cursor',
        'capabilities', 'meta',
    ];

    protected function casts(): array
    {
        return [
            'last_heartbeat_at' => 'datetime',
            'last_sync_at' => 'datetime',
            'capabilities' => 'array',
            'meta' => 'array',
        ];
    }

    public function station(): BelongsTo
    {
        return $this->belongsTo(Station::class);
    }

    public static function findByApiToken(string $token): ?self
    {
        $hash = hash('sha256', $token);

        return static::where('api_token_hash', $hash)->first();
    }

    public function hasCapability(string $capability): bool
    {
        return in_array($capability, $this->capabilities ?? [], true);
    }
}
