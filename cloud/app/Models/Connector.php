<?php

namespace App\Models;

use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Connector extends Model
{
    use HasUuid;

    protected $fillable = [
        'uuid', 'charger_id', 'connector_number', 'ocpp_status',
        'operational_status', 'max_power_kw', 'qr_token_uuid',
        'status_updated_at', 'meta',
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
        return $this->belongsTo(Charger::class);
    }
}
