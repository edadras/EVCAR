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
        'status_updated_at', 'error_code', 'vendor_error_code', 'meta',
    ];

    protected function casts(): array
    {
        return [
            'status_updated_at' => 'datetime',
            'meta' => 'array',
        ];
    }

    public function charger(): BelongsTo
    {
        return $this->belongsTo(EdgeCharger::class, 'charger_id');
    }
}
