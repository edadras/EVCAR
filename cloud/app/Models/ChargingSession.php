<?php

namespace App\Models;

use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChargingSession extends Model
{
    use HasUuid;

    public const FINISHED_STATUSES = ['stopped', 'completed'];

    /** Live readings older than this are not shown as current values. */
    public const LIVE_STALE_SECONDS = 600;

    public function scopeFinished(Builder $query): Builder
    {
        return $query->whereIn($query->qualifyColumn('status'), self::FINISHED_STATUSES);
    }

    /** Finished sessions that actually delivered energy (excludes aborted / failed starts). */
    public function scopeSuccessful(Builder $query): Builder
    {
        return $query->whereIn($query->qualifyColumn('status'), self::FINISHED_STATUSES)->where($query->qualifyColumn('energy_kwh'), '>', 0);
    }

    public function isSuccessful(): bool
    {
        return in_array($this->status, self::FINISHED_STATUSES, true) && (float) $this->energy_kwh > 0;
    }

    public function isFailedStart(): bool
    {
        return in_array($this->status, self::FINISHED_STATUSES, true) && (float) $this->energy_kwh <= 0;
    }

    public function elapsedSeconds(): int
    {
        if ($this->status === 'active') {
            return $this->started_at ? max(0, (int) $this->started_at->diffInSeconds(now())) : 0;
        }
        if ($this->duration_seconds !== null) {
            return (int) $this->duration_seconds;
        }

        return ($this->started_at && $this->stopped_at) ? max(0, (int) $this->started_at->diffInSeconds($this->stopped_at)) : 0;
    }

    /**
     * Measured values only; null when the charger has not reported them.
     *
     * @return array{power_kw: ?float, soc: ?int, voltage_v: ?float, current_a: ?float, energy_kwh: float, elapsed_seconds: int, cost: float}
     */
    public function liveReadings(): array
    {
        $meta = $this->meta ?? [];
        $active = $this->status === 'active';
        $power = $active && isset($meta['power_kw']) ? (float) $meta['power_kw'] : null;
        $soc = isset($meta['soc']) && ($meta['soc_source'] ?? null) === 'charger' && (int) $meta['soc'] > 0
            ? (int) $meta['soc'] : null;

        return [
            'power_kw' => $power,
            'soc' => $soc,
            'voltage_v' => $active && isset($meta['voltage_v']) ? (float) $meta['voltage_v'] : null,
            'current_a' => $active && isset($meta['current_a']) ? (float) $meta['current_a'] : null,
            'energy_kwh' => round((float) $this->energy_kwh, 3),
            'elapsed_seconds' => $this->elapsedSeconds(),
            'cost' => (float) $this->cost_amount,
        ];
    }

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
