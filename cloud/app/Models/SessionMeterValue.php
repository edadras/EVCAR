<?php

namespace App\Models;

use App\Models\Concerns\StoresAppTimezone;
use Illuminate\Database\Eloquent\Model;

class SessionMeterValue extends Model
{
    use StoresAppTimezone;

    protected $fillable = [
        'charging_session_id', 'sampled_at', 'energy_wh', 'power_w', 'voltage_v',
        'current_a', 'soc_percent', 'measurand', 'raw_payload',
    ];

    protected function casts(): array
    {
        return [
            'sampled_at' => 'datetime',
            'raw_payload' => 'array',
        ];
    }
}
