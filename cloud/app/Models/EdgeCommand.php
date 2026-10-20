<?php

namespace App\Models;

use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EdgeCommand extends Model
{
    use HasUuid;

    protected $fillable = [
        'uuid', 'edge_station_id', 'station_id', 'connector_id',
        'charge_point_id', 'connector_number', 'command_type', 'payload',
        'status', 'requested_by', 'result', 'error_message',
        'requested_at', 'dispatched_at', 'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'result' => 'array',
            'requested_at' => 'datetime',
            'dispatched_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function edgeStation(): BelongsTo
    {
        return $this->belongsTo(EdgeStation::class);
    }

    public function station(): BelongsTo
    {
        return $this->belongsTo(Station::class);
    }

    public function connector(): BelongsTo
    {
        return $this->belongsTo(Connector::class);
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }
}
