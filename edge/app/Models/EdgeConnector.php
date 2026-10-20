<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EdgeConnector extends Model
{
    protected $table = 'connectors';

    protected $fillable = [
        'uuid', 'cloud_connector_uuid', 'charger_id', 'connector_number',
        'ocpp_status', 'max_power_kw', 'operational_status',
    ];

    public function charger(): BelongsTo
    {
        return $this->belongsTo(EdgeCharger::class, 'charger_id');
    }
}
