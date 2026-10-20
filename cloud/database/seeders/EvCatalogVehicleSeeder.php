<?php

namespace Database\Seeders;

use App\Models\EvCatalogVehicle;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class EvCatalogVehicleSeeder extends Seeder
{
    public function run(): void
    {
        if (EvCatalogVehicle::query()->exists()) {
            return;
        }

        /** @var array<string, array<string, mixed>> $vehicles */
        $vehicles = require __DIR__.'/data/ev_catalog_vehicles.php';

        $sort = 0;
        foreach ($vehicles as $slug => $entry) {
            $consumption = $entry['consumption'] ?? [];
            $base = (float) ($entry['consumption_base'] ?? $consumption['base'] ?? $consumption['mixed'] ?? 16);

            EvCatalogVehicle::create([
                'uuid' => (string) Str::uuid(),
                'slug' => $slug,
                'name' => $entry['name'] ?? $slug,
                'brand' => $entry['brand'] ?? null,
                'model' => $entry['model'] ?? null,
                'battery_kwh' => $entry['battery_kwh'] ?? 60,
                'mass_kg' => (int) ($entry['mass_kg'] ?? 1750),
                'regen_efficiency' => $entry['regen_efficiency'] ?? 0.65,
                'cd' => $entry['cd'] ?? null,
                'frontal_area_m2' => $entry['frontal_area_m2'] ?? null,
                'consumption_base' => $base,
                'range_wltp_km' => $entry['range_wltp_km'] ?? null,
                'range_real_min_km' => $entry['range_real_min_km'] ?? null,
                'range_real_max_km' => $entry['range_real_max_km'] ?? null,
                'range_worst_km' => $entry['range_worst_km'] ?? null,
                'consumption' => array_merge($consumption, ['base' => $base]),
                'aliases' => $entry['aliases'] ?? [],
                'sort_order' => $sort++,
                'is_active' => true,
            ]);
        }
    }
}
