<?php

namespace App\Services\Navigator;

use App\Models\EvCatalogVehicle;
use Illuminate\Support\Facades\Schema;

class EvVehicleCatalog
{
    /** @return array<string, array<string, mixed>> */
    public static function all(): array
    {
        if (! self::databaseReady()) {
            return [];
        }

        $out = [];
        EvCatalogVehicle::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->each(function (EvCatalogVehicle $row) use (&$out) {
                $out[$row->slug] = $row->toCatalogArray();
            });

        return $out;
    }

    /** @return array<string, mixed>|null */
    public static function find(string $id): ?array
    {
        if (! self::databaseReady()) {
            return null;
        }

        $row = EvCatalogVehicle::query()
            ->where('slug', $id)
            ->where('is_active', true)
            ->first();

        return $row ? self::normalizeEntry($row->slug, $row->toCatalogArray()) : null;
    }

    /** @return array<string, mixed>|null */
    public static function findByLabel(string $label): ?array
    {
        $needle = self::normalizeLabel($label);
        if ($needle === '' || ! self::databaseReady()) {
            return null;
        }

        $rows = EvCatalogVehicle::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get();

        foreach ($rows as $row) {
            $entry = self::normalizeEntry($row->slug, $row->toCatalogArray());
            if (self::normalizeLabel($entry['name']) === $needle) {
                return $entry;
            }
            foreach ($entry['aliases'] as $alias) {
                if (self::normalizeLabel($alias) === $needle) {
                    return $entry;
                }
            }
        }

        foreach ($rows as $row) {
            $entry = self::normalizeEntry($row->slug, $row->toCatalogArray());
            $hay = self::normalizeLabel($entry['name']);
            if (str_contains($hay, $needle) || str_contains($needle, $hay)) {
                return $entry;
            }
            foreach ($entry['aliases'] as $alias) {
                $a = self::normalizeLabel($alias);
                if (str_contains($a, $needle) || str_contains($needle, $a)) {
                    return $entry;
                }
            }
        }

        return null;
    }

    /** @return array<string, mixed>|null */
    public static function findByUserVehicle(?string $brand, ?string $model): ?array
    {
        return self::findByLabel(trim("{$brand} {$model}"));
    }

    /**
     * مصرف پایه واقعی + ضریب سرعت/دما/شیب — یک مدل واحد برای برد و مسیر.
     *
     * @param  array<string, mixed>  $vehicle
     * @param  list<float>  $elevations
     * @return array{
     *   kwh100:float,
     *   base_kwh100:float,
     *   multiplier:float,
     *   profile:string,
     *   source:string,
     *   factors:array<string,float>
     * }
     */
    public static function routeConsumption(
        array $vehicle,
        float $avgSpeedKmh,
        float $avgTempC,
        array $elevations = [],
    ): array {
        $base = (float) ($vehicle['consumption_base'] ?? $vehicle['consumption_kwh100'] ?? 16);
        // شیب فقط در سگمنت‌ها (گرانش) — اینجا فقط سرعت و دما
        $speedMul = 1.0 + max(0.0, ($avgSpeedKmh - 100.0) * 0.002);
        $tempMul = match (true) {
            $avgTempC < 0 => 1.06,
            $avgTempC < 8 => 1.04,
            $avgTempC > 32 => 1.03,
            default => 1.0,
        };
        $multiplier = round($speedMul * $tempMul, 3);

        return [
            'kwh100' => round($base * $multiplier, 2),
            'base_kwh100' => $base,
            'multiplier' => $multiplier,
            'profile' => 'route_adjusted',
            'source' => $vehicle['consumption_source'] ?? 'catalog',
            'factors' => [
                'speed' => round($speedMul, 3),
                'temperature' => round($tempMul, 3),
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $entry
     * @return array<string, mixed>
     */
    public static function toNavigatorVehicle(array $entry, ?float $batteryOverride = null): array
    {
        $base = (float) ($entry['consumption_base'] ?? $entry['consumption']['base'] ?? $entry['consumption']['mixed'] ?? 16);

        return [
            'id' => $entry['id'],
            'name' => $entry['name'],
            'battery_kwh' => (float) ($batteryOverride ?? $entry['battery_kwh'] ?? 60),
            'consumption_base' => $base,
            'consumption_kwh100' => $base,
            'mass_kg' => (float) ($entry['mass_kg'] ?? 1750),
            'cd' => (float) ($entry['cd'] ?? 0.28),
            'frontal_area_m2' => (float) ($entry['frontal_area_m2'] ?? 2.4),
            'regen_efficiency' => (float) ($entry['regen_efficiency'] ?? 0.65),
            'consumption_source' => 'catalog',
            'catalog_id' => $entry['id'],
            'range_wltp_km' => isset($entry['range_wltp_km']) ? (int) $entry['range_wltp_km'] : null,
            'range_real_min_km' => isset($entry['range_real_min_km']) ? (int) $entry['range_real_min_km'] : null,
            'range_real_max_km' => isset($entry['range_real_max_km']) ? (int) $entry['range_real_max_km'] : null,
            'range_worst_km' => isset($entry['range_worst_km']) ? (int) $entry['range_worst_km'] : null,
        ];
    }

    /** @return list<array<string, mixed>> */
    public static function presetsForNavigator(): array
    {
        $entries = [];
        foreach (self::all() as $id => $entry) {
            $normalized = self::normalizeEntry($id, $entry);
            $entries[] = self::toNavigatorVehicle($normalized);
        }

        usort($entries, fn (array $a, array $b) => strcmp($a['name'], $b['name']));

        $presets = $entries;
        $presets[] = [
            'id' => 'custom',
            'name' => 'خودروی سفارشی',
            'battery_kwh' => 60,
            'consumption_base' => 16,
            'consumption_kwh100' => 16,
            'mass_kg' => 1750,
            'cd' => 0.28,
            'frontal_area_m2' => 2.4,
            'regen_efficiency' => 0.65,
            'consumption_source' => 'custom',
        ];

        return $presets;
    }

    /** @param  array<string, mixed>  $entry */
    private static function normalizeEntry(string $id, array $entry): array
    {
        $entry['id'] = $id;
        $entry['aliases'] = $entry['aliases'] ?? [];
        if (! isset($entry['consumption_base'])) {
            $entry['consumption_base'] = (float) ($entry['consumption']['base'] ?? $entry['consumption']['mixed'] ?? 16);
        }

        return $entry;
    }

    private static function normalizeLabel(string $label): string
    {
        $label = trim(mb_strtolower($label));
        $label = str_replace(['‌', 'ي', 'ك'], ['', 'ی', 'ک'], $label);
        $label = preg_replace('/\s+/u', ' ', $label) ?? $label;

        return $label;
    }

    /** @param  list<float>  $elevations */
    private static function totalElevationGainM(array $elevations): float
    {
        $gain = 0.0;
        for ($i = 1; $i < count($elevations); $i++) {
            $dh = ($elevations[$i] ?? 0) - ($elevations[$i - 1] ?? 0);
            if ($dh > 0) {
                $gain += $dh;
            }
        }

        return $gain;
    }

    private static function databaseReady(): bool
    {
        try {
            return Schema::hasTable('ev_catalog_vehicles');
        } catch (\Throwable) {
            return false;
        }
    }
}
