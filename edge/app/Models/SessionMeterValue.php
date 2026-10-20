<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SessionMeterValue extends Model
{
    protected $fillable = [
        'charging_session_id', 'sampled_at', 'energy_wh', 'power_w', 'voltage_v',
        'current_a', 'soc_percent', 'pending_sync', 'raw_payload',
    ];

    protected function casts(): array
    {
        return [
            'sampled_at' => 'datetime',
            'pending_sync' => 'boolean',
            'raw_payload' => 'array',
        ];
    }
}
