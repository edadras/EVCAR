<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExternalStation extends Model
{
    public const CONNECTORS = [
        'GBT' => 'GB/T',
        'CCS2' => 'CCS2',
        'CCS1' => 'CCS1',
        'CHAdeMO' => 'CHAdeMO',
        'Type2' => 'Type 2',
        'Type1' => 'Type 1',
    ];

    protected $fillable = [
        'ev_operator_id', 'name', 'latitude', 'longitude', 'city', 'address',
        'dc_connectors', 'ac_connectors', 'dc_max_kw', 'ac_max_kw', 'ports',
        'hours', 'facilities', 'tariff_toman', 'phone', 'image_url', 'notes',
        'source', 'source_ref', 'is_active',
    ];

    protected $casts = [
        'latitude' => 'float',
        'longitude' => 'float',
        'dc_connectors' => 'array',
        'ac_connectors' => 'array',
        'dc_max_kw' => 'float',
        'ac_max_kw' => 'float',
        'ports' => 'integer',
        'tariff_toman' => 'integer',
        'is_active' => 'boolean',
    ];

    public function operator(): BelongsTo
    {
        return $this->belongsTo(EvOperator::class, 'ev_operator_id');
    }

    public function connectorLabel(): string
    {
        $fmt = fn (array $list) => implode('، ', array_map(fn ($c) => self::CONNECTORS[$c] ?? $c, $list));
        $parts = [];
        if ($this->dc_connectors) {
            $parts[] = 'DC '.$fmt($this->dc_connectors);
        }
        if ($this->ac_connectors) {
            $parts[] = 'AC '.$fmt($this->ac_connectors);
        }

        return implode(' · ', $parts);
    }

    public function maxKw(): ?float
    {
        return $this->dc_max_kw ?: $this->ac_max_kw;
    }
}
