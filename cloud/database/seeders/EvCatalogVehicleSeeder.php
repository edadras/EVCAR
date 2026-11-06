<?php

namespace Database\Seeders;

use App\Models\EvCatalogVehicle;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class EvCatalogVehicleSeeder extends Seeder
{
    /** نسبت برد واقعی (حداکثر، حداقل، بدترین حالت) به برد رسمی در هر استاندارد */
    private const REAL_RANGE_FACTORS = [
        'CLTC' => [0.75, 0.64, 0.52],
        'NEDC' => [0.76, 0.65, 0.53],
        'WLTP' => [0.90, 0.78, 0.64],
        'EPA' => [0.95, 0.82, 0.68],
    ];
    public function run(): void
    {
        if (EvCatalogVehicle::query()->withTrashed()->exists()) {
            return;
        }

        self::insertAll();
    }

    public static function insertAll(): int
    {
        /** @var array<string, array<string, mixed>> $vehicles */
        $vehicles = require __DIR__.'/data/ev_catalog_vehicles.php';

        $sort = 0;
        foreach ($vehicles as $slug => $entry) {
            EvCatalogVehicle::create(self::attributes($slug, $entry, $sort++));
        }

        return $sort;
    }

    /**
     * @param  array<string, mixed>  $entry
     * @return array<string, mixed>
     */
    public static function attributes(string $slug, array $entry, int $sort): array
    {
        $battery = (float) ($entry['battery_kwh'] ?? 60);
        $official = isset($entry['range_official_km']) ? (int) $entry['range_official_km'] : null;
        $standard = $entry['range_standard'] ?? null;
        [$fMax, $fMin, $fWorst] = self::REAL_RANGE_FACTORS[$standard] ?? self::REAL_RANGE_FACTORS['CLTC'];

        $realMax = (int) ($entry['range_real_max_km'] ?? ($official ? round($official * $fMax / 5) * 5 : 0));
        $realMin = (int) ($entry['range_real_min_km'] ?? ($official ? round($official * $fMin / 5) * 5 : round($realMax * 0.85)));
        $worst = (int) ($entry['range_worst_km'] ?? ($official ? round($official * $fWorst / 5) * 5 : round($realMax * 0.7)));
        if (isset($entry['range_real_max_km']) && ! isset($entry['range_worst_km'])) {
            $worst = (int) round($realMin * 0.82 / 5) * 5;
        }

        $base = $realMax > 0
            ? EvCatalogVehicle::consumptionBaseFromRange($battery, $realMax)
            : (float) ($entry['consumption_base'] ?? 16);
        $consumption = ['base' => $base];
        if ($worst > 0) {
            $consumption['worst'] = round($battery * EvCatalogVehicle::USABLE_BATTERY_FRACTION / ($worst / 100), 1);
        }

        $frontal = $entry['frontal_area_m2'] ?? null;
        if ($frontal === null && isset($entry['dims'][1], $entry['dims'][2])) {
            $frontal = round($entry['dims'][1] * $entry['dims'][2] * 0.84 / 1_000_000, 2);
        }

        return [
            'uuid' => (string) Str::uuid(),
            'slug' => $slug,
            'name' => $entry['name'] ?? $slug,
            'brand' => $entry['brand'] ?? null,
            'model' => $entry['model'] ?? null,
            'importer' => $entry['importer'] ?? null,
            'battery_kwh' => $battery,
            'battery_chemistry' => $entry['battery_chemistry'] ?? null,
            'mass_kg' => (int) ($entry['mass_kg'] ?? 1750),
            'motor_kw' => $entry['motor_kw'] ?? null,
            'drivetrain' => $entry['drivetrain'] ?? null,
            'regen_efficiency' => $entry['regen_efficiency'] ?? 0.66,
            'cd' => $entry['cd'] ?? null,
            'frontal_area_m2' => $frontal,
            'consumption_base' => $base,
            'range_wltp_km' => $entry['range_wltp_km'] ?? ($standard === 'WLTP' ? $official : null),
            'range_official_km' => $official,
            'range_standard' => $standard,
            'range_real_min_km' => $realMin ?: null,
            'range_real_max_km' => $realMax ?: null,
            'range_worst_km' => $worst ?: null,
            'dc_connector' => $entry['dc_connector'] ?? 'GBT',
            'ac_connector' => $entry['ac_connector'] ?? 'GBT',
            'dc_max_kw' => $entry['dc_max_kw'] ?? null,
            'ac_max_kw' => $entry['ac_max_kw'] ?? null,
            'consumption' => $consumption,
            'aliases' => $entry['aliases'] ?? [],
            'notes' => $entry['notes'] ?? null,
            'sort_order' => $sort,
            'is_active' => (bool) ($entry['active'] ?? true),
        ];
    }
}
