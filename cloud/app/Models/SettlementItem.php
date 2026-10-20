<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SettlementItem extends Model
{
    protected $fillable = [
        'settlement_id', 'station_id', 'gross_revenue', 'partner_share', 'energy_kwh', 'session_count',
    ];

    protected function casts(): array
    {
        return [
            'gross_revenue' => 'decimal:2',
            'partner_share' => 'decimal:2',
            'energy_kwh' => 'decimal:3',
        ];
    }

    public function settlement(): BelongsTo
    {
        return $this->belongsTo(Settlement::class);
    }

    public function station(): BelongsTo
    {
        return $this->belongsTo(Station::class);
    }
}
