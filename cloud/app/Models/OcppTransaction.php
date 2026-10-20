<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OcppTransaction extends Model
{
    protected $fillable = [
        'charge_point_id', 'connector_number', 'id_tag', 'id_tag_status',
        'meter_start_wh', 'meter_stop_wh', 'started_at', 'stopped_at',
        'stop_reason', 'charging_session_id',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'stopped_at' => 'datetime',
        ];
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(ChargingSession::class, 'charging_session_id');
    }
}
