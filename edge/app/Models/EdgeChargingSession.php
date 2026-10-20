<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EdgeChargingSession extends Model
{
    protected $table = 'charging_sessions';

    protected $fillable = [
        'uuid', 'cloud_session_uuid', 'charger_id', 'connector_id',
        'cached_user_uuid', 'cached_vehicle_uuid', 'cached_tariff_uuid',
        'pending_sync', 'synced_at',
        'id_tag', 'auth_method', 'status', 'started_at', 'stopped_at',
        'energy_kwh', 'duration_seconds', 'cost_amount', 'currency', 'meta',
        'ocpp_transaction_id', 'meter_start_wh', 'meter_stop_wh', 'stop_reason',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'stopped_at' => 'datetime',
            'energy_kwh' => 'decimal:3',
            'cost_amount' => 'decimal:2',
            'meta' => 'array',
            'pending_sync' => 'boolean',
        ];
    }

    public function charger(): BelongsTo
    {
        return $this->belongsTo(EdgeCharger::class, 'charger_id');
    }

    public function connector(): BelongsTo
    {
        return $this->belongsTo(EdgeConnector::class, 'connector_id');
    }
}
