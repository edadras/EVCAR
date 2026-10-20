<?php

namespace App\Models;

use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Charger extends Model
{
    use HasUuid, SoftDeletes;

    protected $fillable = [
        'uuid', 'station_id', 'charge_point_id', 'vendor', 'model',
        'serial_number', 'firmware_version', 'max_power_kw', 'connector_type',
        'is_online', 'last_heartbeat_at', 'status', 'meta',
        'connection_mode', 'connected_at', 'last_boot_at', 'ocpp_status', 'error_code',
    ];

    protected function casts(): array
    {
        return [
            'is_online' => 'boolean',
            'last_heartbeat_at' => 'datetime',
            'connected_at' => 'datetime',
            'last_boot_at' => 'datetime',
            'meta' => 'array',
        ];
    }

    /**
     * آیا این شارژر مستقیم به سرور OCPP ابری وصل می‌شود؟
     * auto: اگر ایستگاه Edge ندارد مستقیم، وگرنه از طریق Edge.
     */
    public function connectsDirectly(): bool
    {
        return match ($this->connection_mode ?? 'auto') {
            'direct' => true,
            'edge' => false,
            default => ! $this->station?->edgeStation()->exists(),
        };
    }

    public function station(): BelongsTo
    {
        return $this->belongsTo(Station::class);
    }

    public function connectors(): HasMany
    {
        return $this->hasMany(Connector::class);
    }
}
