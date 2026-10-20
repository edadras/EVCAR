<?php

namespace Database\Seeders;

use App\Models\EvCatalogVehicle;
use Illuminate\Database\Seeder;

class SyncEvCatalogFromSeedData extends Seeder
{
    public function run(): void
    {
        /** @var array<string, array<string, mixed>> $vehicles */
        $vehicles = require __DIR__.'/data/ev_catalog_vehicles.php';

        foreach ($vehicles as $slug => $entry) {
            $row = EvCatalogVehicle::query()->where('slug', $slug)->first();
            if (! $row) {
                continue;
            }

            $battery = (float) ($entry['battery_kwh'] ?? $row->battery_kwh);
            $rangeMax = (int) ($entry['range_real_max_km'] ?? $row->range_real_max_km ?? 0);
            $rangeMin = (int) ($entry['range_real_min_km'] ?? $row->range_real_min_km ?? 0);
            $consumption = $entry['consumption'] ?? [];

            if ($rangeMax <= 0 && isset($consumption['mixed'])) {
                $rangeMax = (int) round($battery * EvCatalogVehicle::USABLE_BATTERY_FRACTION / ((float) $consumption['mixed'] / 100));
            }
            if ($rangeMin <= 0 && $rangeMax > 0) {
                $rangeMin = (int) round($rangeMax * 0.82);
            }

            $base = $rangeMax > 0
                ? EvCatalogVehicle::consumptionBaseFromRange($battery, $rangeMax)
                : (float) ($entry['consumption_base'] ?? $consumption['base'] ?? $consumption['mixed'] ?? 16);

            $row->update([
                'battery_kwh' => $battery,
                'consumption_base' => $base,
                'range_wltp_km' => $entry['range_wltp_km'] ?? $row->range_wltp_km,
                'range_real_min_km' => $rangeMin > 0 ? $rangeMin : $row->range_real_min_km,
                'range_real_max_km' => $rangeMax > 0 ? $rangeMax : $row->range_real_max_km,
                'range_worst_km' => $entry['range_worst_km'] ?? $row->range_worst_km,
                'consumption' => array_merge($consumption, ['base' => $base]),
            ]);
        }
    }
}
