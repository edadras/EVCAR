<?php

namespace App\Models;

use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Reservation extends Model
{
    use HasUuid;

    protected $fillable = [
        'uuid', 'user_id', 'station_id', 'charger_id', 'connector_id',
        'status', 'reserved_from', 'reserved_until', 'checked_in_at',
        'cancelled_at', 'edge_reservation_uuid', 'meta',
    ];

    protected function casts(): array
    {
        return [
            'reserved_from' => 'datetime',
            'reserved_until' => 'datetime',
            'checked_in_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'meta' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function station(): BelongsTo
    {
        return $this->belongsTo(Station::class);
    }

    public function charger(): BelongsTo
    {
        return $this->belongsTo(Charger::class);
    }

    public function connector(): BelongsTo
    {
        return $this->belongsTo(Connector::class);
    }
}
