<?php

namespace App\Models;

use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Station extends Model
{
    use HasUuid, SoftDeletes;

    protected $fillable = [
        'uuid', 'code', 'name', 'partner_id', 'type', 'status',
        'address', 'city', 'province', 'latitude', 'longitude',
        'timezone', 'offline_mode_enabled', 'local_hostname', 'wifi_ssid',
        'operating_hours', 'meta',
    ];

    protected function casts(): array
    {
        return [
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'offline_mode_enabled' => 'boolean',
            'operating_hours' => 'array',
            'meta' => 'array',
        ];
    }

    public function partner(): BelongsTo
    {
        return $this->belongsTo(Partner::class);
    }

    public function chargers(): HasMany
    {
        return $this->hasMany(Charger::class);
    }

    public function chargingSessions(): HasMany
    {
        return $this->hasMany(ChargingSession::class);
    }

    public function reservations(): HasMany
    {
        return $this->hasMany(Reservation::class);
    }

    public function edgeStation(): HasOne
    {
        return $this->hasOne(EdgeStation::class);
    }

    public function qrTokens(): HasMany
    {
        return $this->hasMany(QrToken::class);
    }
}
