<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EdgeCharger extends Model
{
    protected $table = 'chargers';

    protected $fillable = [
        'uuid', 'cloud_charger_uuid', 'charge_point_id', 'max_power_kw', 'is_online', 'status', 'meta',
    ];

    protected function casts(): array
    {
        return [
            'is_online' => 'boolean',
            'meta' => 'array',
        ];
    }

    public function connectors(): HasMany
    {
        return $this->hasMany(EdgeConnector::class, 'charger_id');
    }
}
