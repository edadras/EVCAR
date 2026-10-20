<?php

namespace App\Models;

use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChargingSession extends Model
{
    use HasUuid;

    protected $fillable = [
        'uuid', 'station_id', 'charger_id', 'connector_id', 'user_id',
        'vehicle_id', 'tariff_id', 'edge_station_uuid', 'edge_session_uuid',
        'ocpp_transaction_id', 'id_tag', 'auth_method', 'status',
        'started_at', 'stopped_at', 'meter_start_wh', 'meter_stop_wh',
        'energy_kwh', 'duration_seconds', 'cost_amount', 'currency',
        'stop_reason', 'synced_from_edge', 'meta',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'stopped_at' => 'datetime',
            'energy_kwh' => 'decimal:3',
            'cost_amount' => 'decimal:2',
            'synced_from_edge' => 'boolean',
            'meta' => 'array',
        ];
    }

    public function station(): BelongsTo
    {
        return $this->belongsTo(Station::class);
    }

    public function charger(): BelongsTo
    {
        return $this->belongsTo(Charger::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function connector(): BelongsTo
    {
        return $this->belongsTo(Connector::class);
    }

    public function tariff(): BelongsTo
    {
        return $this->belongsTo(Tariff::class);
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }
}
