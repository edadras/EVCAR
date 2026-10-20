<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TariffRule extends Model
{
    protected $fillable = [
        'tariff_id', 'rule_type', 'day_of_week', 'start_time', 'end_time',
        'price_per_kwh', 'price_per_minute', 'multiplier', 'priority',
    ];

    public function tariff(): BelongsTo
    {
        return $this->belongsTo(Tariff::class);
    }
}
