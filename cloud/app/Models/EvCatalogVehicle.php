<?php

namespace App\Models;

use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class EvCatalogVehicle extends Model
{
    use HasUuid, SoftDeletes;

    protected $fillable = [
        'uuid',
        'slug',
        'name',
        'brand',
        'model',
        'battery_kwh',
        'mass_kg',
        'regen_efficiency',
        'cd',
        'frontal_area_m2',
        'consumption_base',
        'range_wltp_km',
        'range_real_min_km',
        'range_real_max_km',
        'range_worst_km',
        'consumption',
        'aliases',
        'sort_order',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'battery_kwh' => 'decimal:2',
            'consumption_base' => 'decimal:2',
            'regen_efficiency' => 'decimal:3',
            'cd' => 'decimal:3',
            'frontal_area_m2' => 'decimal:2',
            'consumption' => 'array',
            'aliases' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public const USABLE_BATTERY_FRACTION = 0.90;

    public function resolvedConsumptionBase(): float
    {
        if ($this->range_real_max_km > 0 && $this->battery_kwh > 0) {
            return self::consumptionBaseFromRange(
                (float) $this->battery_kwh,
                (int) $this->range_real_max_km,
            );
        }

        if ($this->consumption_base !== null) {
            return (float) $this->consumption_base;
        }

        $consumption = $this->consumption ?? [];

        return (float) ($consumption['base'] ?? $consumption['mixed'] ?? 16.0);
    }

    public static function consumptionBaseFromRange(float $batteryKwh, int $rangeRealMaxKm): float
    {
        if ($rangeRealMaxKm <= 0 || $batteryKwh <= 0) {
            return 16.0;
        }

        $usableKwh = $batteryKwh * self::USABLE_BATTERY_FRACTION;

        return round($usableKwh / ($rangeRealMaxKm / 100), 2);
    }

    /** @return array<string, mixed> */
    public function toCatalogArray(): array
    {
        $consumption = $this->consumption ?? [];
        $base = $this->resolvedConsumptionBase();

        $data = [
            'name' => $this->name,
            'brand' => $this->brand,
            'model' => $this->model,
            'battery_kwh' => (float) $this->battery_kwh,
            'consumption_base' => $base,
            'mass_kg' => (float) $this->mass_kg,
            'regen_efficiency' => (float) $this->regen_efficiency,
            'aliases' => $this->aliases ?? [],
            'consumption' => array_merge($consumption, ['base' => $base]),
        ];

        if ($this->cd !== null) {
            $data['cd'] = (float) $this->cd;
        }
        if ($this->frontal_area_m2 !== null) {
            $data['frontal_area_m2'] = (float) $this->frontal_area_m2;
        }
        if ($this->range_wltp_km !== null) {
            $data['range_wltp_km'] = (int) $this->range_wltp_km;
        }
        if ($this->range_real_min_km !== null) {
            $data['range_real_min_km'] = (int) $this->range_real_min_km;
        }
        if ($this->range_real_max_km !== null) {
            $data['range_real_max_km'] = (int) $this->range_real_max_km;
        }
        if ($this->range_worst_km !== null) {
            $data['range_worst_km'] = (int) $this->range_worst_km;
        }

        return $data;
    }
}
