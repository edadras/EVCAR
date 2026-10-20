<?php

namespace App\Services\Navigator;

use App\Models\Station;
use App\Models\User;
use App\Models\Vehicle;
use App\Support\Geo;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class EvNavigatorService
{
    /** @return array<string, mixed> */
    public function analyze(User $user, array $input): array
    {
        $apiUrl = config('safircharge.ev_navigator.api_url');
        if ($apiUrl !== '') {
            $proxied = $this->proxyToApi($apiUrl, $input);
            if ($proxied !== null) {
                return $proxied;
            }
        }

        $result = $this->analyzeLite($user, $input);
        if ($apiUrl !== '') {
            $result['source'] = 'lite_fallback';
            $result['api_error'] = 'سرور پیشرفته مسیریاب در دسترس نبود؛ تحلیل با حالت ابری انجام شد.';
        }

        return $result;
    }

    /** @return array<string, mixed>|null */
    private function proxyToApi(string $baseUrl, array $input): ?array
    {
        try {
            $response = Http::timeout((int) config('safircharge.ev_navigator.timeout_seconds', 30))
                ->acceptJson()
                ->post("{$baseUrl}/api/v1/navigate/analyze", $input);

            if (! $response->successful()) {
                Log::warning('EV Navigator API error', ['status' => $response->status(), 'body' => $response->body()]);

                return null;
            }

            $body = $response->json();
            $body['source'] = 'api';

            return $body;
        } catch (\Throwable $e) {
            Log::warning('EV Navigator API unreachable', ['message' => $e->getMessage()]);

            return null;
        }
    }

    /** @return array<string, mixed> */
    private function analyzeLite(User $user, array $input): array
    {
        $origin = $input['origin'];
        $destination = $input['destination'];
        $vehicle = $this->resolveVehicle($user, $input);
        $soc = max(5, min(100, (int) ($input['current_soc'] ?? 80)));
        $targetSoc = max(10, min(100, (int) ($input['target_soc'] ?? max(20, $soc))));

        $batteryKwh = (float) $vehicle['battery_kwh'];

        $directRoute = $this->fetchOsrmRoute($origin, $destination);
        $directDistanceKm = round($directRoute['distance_m'] / 1000, 1);
        $planning = $this->analyzeGeometry(
            $this->smartSampleRoute($directRoute['coordinates'], 120),
            $directRoute,
            $vehicle,
            $batteryKwh,
            $soc,
            $targetSoc,
        );
        $viaWaypoints = $this->waypointsFromChargePlan(
            $planning['charge_plan'],
            $planning['candidates'] ?? $planning['chargers'],
        );

        $routeMode = 'direct';
        $route = $directRoute;
        if ($viaWaypoints !== []) {
            $route = $this->fetchOsrmRoute($origin, $destination, $viaWaypoints);
            $routeMode = 'via_chargers';
        }

        $points = $this->smartSampleRoute($route['coordinates'], 120);
        $geometry = $this->analyzeGeometry($points, $route, $vehicle, $batteryKwh, $soc, $targetSoc);
        $elevationData = $geometry['elevation_data'];
        $elevations = $elevationData['values'];
        $weatherSamples = $geometry['weather_samples'];
        $avgSpeedKmh = $geometry['avg_speed_kmh'];
        $segments = $geometry['segments'];
        $chargers = $geometry['chargers'];
        $needsChargeTrip = $geometry['needs_charge'];
        $chargeSimulation = $geometry['charge_simulation'];
        $chargePlan = $chargeSimulation['stops'];
        $totalKwh = array_sum(array_column($segments, 'consumption_kwh'));
        $arrivalSocDirect = $segments !== [] ? (float) end($segments)['soc_after'] : (float) $soc;
        $arrivalSocPlanned = $chargePlan !== []
            ? (float) $chargeSimulation['arrival_soc']
            : $arrivalSocDirect;
        $direction = $this->routeDirectionLabel($origin, $destination);
        $verdict = $this->buildVerdict(
            $arrivalSocDirect,
            $arrivalSocPlanned,
            $targetSoc,
            $soc,
            $chargers,
            $chargePlan,
            $segments,
            (int) ($geometry['corridor_charger_count'] ?? count($chargers)),
            $geometry['first_charge_km'] ?? null,
            (int) ($geometry['estimated_charge_stops'] ?? 0),
            $batteryKwh,
            $geometry['range_km_on_start'] ?? null,
        );

        $elevationProfile = $this->buildElevationProfile($points, $elevations);
        $socProfile = $this->buildSocProfile($segments, $soc);
        $weatherMid = $weatherSamples[(int) floor(count($weatherSamples) / 2)] ?? $weatherSamples[0];

        return [
            'source' => 'lite',
            'route' => [
                'distance_km' => round($route['distance_m'] / 1000, 1),
                'direct_distance_km' => $directDistanceKm,
                'detour_km' => $routeMode === 'via_chargers'
                    ? max(0, round($route['distance_m'] / 1000 - $directDistanceKm, 1))
                    : 0,
                'duration_min' => (int) round($route['duration_s'] / 60),
                'direction' => $direction,
                'mode' => $routeMode,
                'origin_label' => (string) ($origin['label'] ?? 'مبدا'),
                'destination_label' => (string) ($destination['label'] ?? 'مقصد'),
                'waypoints' => $viaWaypoints,
                'polyline' => array_map(fn ($p) => ['lat' => $p[0], 'lng' => $p[1]], $points),
            ],
            'data_sources' => $this->buildDataSources($elevationData['estimated']),
            'segments' => $segments,
            'elevation_profile' => $elevationProfile,
            'soc_profile' => $socProfile,
            'summary' => [
                'total_kwh' => round($totalKwh, 2),
                'arrival_soc' => round($arrivalSocDirect, 1),
                'arrival_soc_planned' => round($arrivalSocPlanned, 1),
                'min_soc_on_route' => $verdict['min_soc_on_route'],
                'needs_charge' => $verdict['needs_charge'],
                'can_reach_destination' => $verdict['can_reach_destination'],
                'can_reach_with_plan' => $verdict['can_reach_with_plan'],
                'target_soc' => $targetSoc,
                'charger_count' => count($chargers),
                'corridor_charger_count' => $geometry['corridor_charger_count'] ?? count($chargers),
                'needs_charge_trip' => $needsChargeTrip,
                'first_charge_km' => $geometry['first_charge_km'],
                'stations_missing' => $geometry['stations_missing'] ?? false,
                'estimated_charge_stops' => $geometry['estimated_charge_stops'] ?? 0,
                'range_km_on_start' => $geometry['range_km_on_start'] ?? null,
                'consumption_per_100km' => $geometry['consumption_per_100km'] ?? (float) $vehicle['consumption_kwh100'],
                'consumption_base_kwh100' => $geometry['consumption_base_kwh100'] ?? (float) ($vehicle['consumption_base'] ?? $vehicle['consumption_kwh100']),
                'consumption_multiplier' => $geometry['consumption_multiplier'] ?? 1.0,
                'consumption_factors' => $geometry['consumption_factors'] ?? null,
                'consumption_profile' => $geometry['consumption_profile'] ?? 'route_adjusted',
                'consumption_source' => $geometry['consumption_source'] ?? ($vehicle['consumption_source'] ?? 'estimate'),
                'charge_stops' => count($chargePlan),
                'avg_temp_c' => round($weatherMid['temperature_c'], 1),
                'wind_ms' => round($weatherMid['wind_speed_ms'], 1),
                'battery_kwh' => $batteryKwh,
                'reserve_soc' => max(10, $targetSoc),
                'usable_kwh_on_start' => round($batteryKwh * max(0, ($soc - max(10, $targetSoc)) / 100), 1),
                'start_soc' => $soc,
                'avg_speed_kmh' => round($avgSpeedKmh, 0),
                'reference_range_km' => $vehicle['range_real_max_km'] ?? null,
            ],
            'verdict' => $verdict,
            'charge_plan' => $chargePlan,
            'chargers' => $chargers,
            'weather' => $weatherMid,
            'vehicle' => $vehicle,
        ];
    }

    /** @return array<string, mixed> */
    private function resolveVehicle(User $user, array $input): array
    {
        if (! empty($input['vehicle_uuid'])) {
            $v = Vehicle::where('user_id', $user->id)->where('uuid', $input['vehicle_uuid'])->first();
            if ($v) {
                $meta = $v->meta ?? [];
                $catalog = EvVehicleCatalog::findByUserVehicle($v->brand, $v->model);

                if ($catalog) {
                    $vehicle = EvVehicleCatalog::toNavigatorVehicle(
                        $catalog,
                        $v->battery_kwh ? (float) $v->battery_kwh : null,
                    );
                } else {
                    $vehicle = [
                        'id' => 'user_vehicle',
                        'name' => trim("{$v->brand} {$v->model}"),
                        'battery_kwh' => (float) ($v->battery_kwh ?: 60),
                        'consumption_kwh100' => (float) ($meta['consumption_kwh100'] ?? 16.0),
                        'consumption_profiles' => null,
                        'mass_kg' => (float) ($meta['mass_kg'] ?? 1750),
                        'cd' => (float) ($meta['cd'] ?? 0.28),
                        'frontal_area_m2' => (float) ($meta['frontal_area_m2'] ?? 2.4),
                        'regen_efficiency' => (float) ($meta['regen_efficiency'] ?? 0.65),
                        'consumption_source' => ! empty($meta['consumption_kwh100']) ? 'user_meta' : 'estimate',
                    ];
                }

                if (! empty($meta['consumption_kwh100'])) {
                    $vehicle['consumption_kwh100'] = (float) $meta['consumption_kwh100'];
                    $vehicle['consumption_source'] = 'user_meta';
                }

                return $vehicle;
            }
        }

        $presetId = $input['vehicle_preset'] ?? 'byd_atto3';

        if ($presetId === 'custom') {
            return [
                'id' => 'custom',
                'name' => 'خودروی سفارشی',
                'battery_kwh' => (float) ($input['battery_kwh'] ?? 60),
                'consumption_kwh100' => (float) ($input['consumption_kwh100'] ?? 16),
                'consumption_profiles' => null,
                'mass_kg' => (float) ($input['mass_kg'] ?? 1750),
                'cd' => (float) ($input['cd'] ?? 0.28),
                'frontal_area_m2' => (float) ($input['frontal_area_m2'] ?? 2.4),
                'regen_efficiency' => (float) ($input['regen_efficiency'] ?? 0.65),
                'consumption_source' => 'custom',
            ];
        }

        $catalog = EvVehicleCatalog::find($presetId);
        if ($catalog) {
            $vehicle = EvVehicleCatalog::toNavigatorVehicle($catalog);
            if (! empty($input['battery_kwh'])) {
                $vehicle['battery_kwh'] = (float) $input['battery_kwh'];
            }
            if (! empty($input['consumption_kwh100'])) {
                $vehicle['consumption_kwh100'] = (float) $input['consumption_kwh100'];
                $vehicle['consumption_source'] = 'custom';
            }

            return $vehicle;
        }

        return [
            'id' => $presetId,
            'name' => 'خودروی برقی',
            'battery_kwh' => (float) ($input['battery_kwh'] ?? 60),
            'consumption_kwh100' => (float) ($input['consumption_kwh100'] ?? 16),
            'consumption_profiles' => null,
            'mass_kg' => (float) ($input['mass_kg'] ?? 1750),
            'cd' => (float) ($input['cd'] ?? 0.28),
            'frontal_area_m2' => (float) ($input['frontal_area_m2'] ?? 2.4),
            'regen_efficiency' => (float) ($input['regen_efficiency'] ?? 0.65),
            'consumption_source' => 'estimate',
        ];
    }

    /**
     * @param  list<array{lat:float,lng:float,label?:string}>  $via
     * @return array{distance_m:int,duration_s:int,coordinates:list<array{0:float,1:float}>}
     */
    private function fetchOsrmRoute(array $origin, array $destination, array $via = []): array
    {
        $parts = [sprintf('%f,%f', $origin['lng'], $origin['lat'])];
        foreach ($via as $wp) {
            $parts[] = sprintf('%f,%f', $wp['lng'], $wp['lat']);
        }
        $parts[] = sprintf('%f,%f', $destination['lng'], $destination['lat']);
        $url = 'https://router.project-osrm.org/route/v1/driving/'.implode(';', $parts);

        $response = Http::timeout(20)->get($url, [
            'overview' => 'full',
            'geometries' => 'geojson',
            'steps' => 'false',
        ]);

        if (! $response->successful()) {
            throw new \RuntimeException('مسیریابی OSRM در دسترس نیست.');
        }

        $route = $response->json('routes.0');
        if (! $route) {
            throw new \RuntimeException('مسیری برای این مبدا و مقصد یافت نشد.');
        }

        $coords = $route['geometry']['coordinates'] ?? [];

        return [
            'distance_m' => (int) ($route['distance'] ?? 0),
            'duration_s' => (int) ($route['duration'] ?? 0),
            'coordinates' => array_map(fn ($c) => [(float) $c[1], (float) $c[0]], $coords),
        ];
    }

    /**
     * @param  list<array{0:float,1:float}>  $points
     * @param  array{distance_m:int,duration_s:int}  $route
     * @return array<string, mixed>
     */
    private function analyzeGeometry(
        array $points,
        array $route,
        array $vehicle,
        float $batteryKwh,
        int $soc,
        int $targetSoc,
    ): array {
        $elevationData = $this->fetchElevations($points);
        $elevations = $elevationData['values'];
        $weatherSamples = $this->fetchWeatherAlongRoute($points);
        $avgSpeedKmh = $route['distance_m'] > 0 && $route['duration_s'] > 0
            ? ($route['distance_m'] / 1000) / ($route['duration_s'] / 3600)
            : 80.0;

        $avgTempC = $weatherSamples !== []
            ? array_sum(array_column($weatherSamples, 'temperature_c')) / count($weatherSamples)
            : 20.0;
        $consumptionCtx = EvVehicleCatalog::routeConsumption($vehicle, $avgSpeedKmh, $avgTempC, $elevations);
        $vehicleForSegments = array_merge($vehicle, [
            'consumption_kwh100' => $consumptionCtx['kwh100'],
            'consumption_base_kwh100' => $consumptionCtx['base_kwh100'],
        ]);

        $segments = $this->buildSegments($points, $elevations, $vehicleForSegments, $weatherSamples, $avgSpeedKmh);
        $segments = $this->attachSocToSegments($segments, $batteryKwh, $soc);
        $corridorChargers = $this->stationsAlongRoute($points);
        $chargerContext = $this->resolveRelevantChargers($corridorChargers, $segments, $vehicle, $batteryKwh, $soc, $targetSoc);
        $chargeSimulation = $this->simulateChargePlan(
            $segments,
            $chargerContext['candidates'],
            $batteryKwh,
            $soc,
            $targetSoc,
        );

        return [
            'elevation_data' => $elevationData,
            'weather_samples' => $weatherSamples,
            'avg_speed_kmh' => $avgSpeedKmh,
            'segments' => $segments,
            'chargers' => $chargerContext['display'],
            'candidates' => $chargerContext['candidates'],
            'corridor_charger_count' => count($corridorChargers),
            'needs_charge' => $chargerContext['needs_charge'],
            'stations_missing' => $chargerContext['stations_missing'],
            'estimated_charge_stops' => $chargerContext['estimated_charge_stops'],
            'range_km_on_start' => $chargerContext['range_km_on_start'],
            'consumption_per_100km' => $chargerContext['consumption_per_100km'],
            'first_charge_km' => $chargerContext['first_charge_km'],
            'consumption_profile' => $consumptionCtx['profile'],
            'consumption_source' => $consumptionCtx['source'],
            'consumption_base_kwh100' => $consumptionCtx['base_kwh100'],
            'consumption_multiplier' => $consumptionCtx['multiplier'],
            'consumption_factors' => $consumptionCtx['factors'],
            'charge_plan' => $chargeSimulation['stops'],
            'charge_simulation' => $chargeSimulation,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $chargePlan
     * @param  list<array<string, mixed>>  $chargers
     * @return list<array{lat:float,lng:float,label:string,uuid:string}>
     */
    private function waypointsFromChargePlan(array $chargePlan, array $chargers): array
    {
        if ($chargePlan === []) {
            return [];
        }

        $byUuid = [];
        foreach ($chargers as $ch) {
            $byUuid[$ch['uuid']] = $ch;
        }

        $waypoints = [];
        foreach ($chargePlan as $stop) {
            $ch = $byUuid[$stop['station_uuid'] ?? ''] ?? null;
            if (! $ch) {
                continue;
            }
            $waypoints[] = [
                'uuid' => $ch['uuid'],
                'lat' => (float) $ch['lat'],
                'lng' => (float) $ch['lng'],
                'label' => (string) $ch['name'],
            ];
        }

        return $waypoints;
    }

    /**
     * Ramer-Douglas-Peucker — کاهش نقاط مسیر با حفظ پیچ‌ها.
     *
     * @param  list<array{0:float,1:float}>  $waypoints
     * @return list<array{0:float,1:float}>
     */
    private function smartSampleRoute(array $waypoints, int $maxPoints = 120, float $epsilon = 0.001): array
    {
        if (count($waypoints) <= 2) {
            return $waypoints;
        }

        $reduced = $this->rdp($waypoints, $epsilon);
        if (count($reduced) > $maxPoints) {
            $reduced = $this->rdp($waypoints, $epsilon * 3);
        }
        if (count($reduced) > $maxPoints) {
            $step = max(1, (int) floor(count($waypoints) / $maxPoints));
            $reduced = [];
            foreach ($waypoints as $i => $c) {
                if ($i % $step === 0 || $i === count($waypoints) - 1) {
                    $reduced[] = $c;
                }
            }
        }

        return $reduced;
    }

    /** @param  list<array{0:float,1:float}>  $points */
    /** @return list<array{0:float,1:float}> */
    private function rdp(array $points, float $epsilon): array
    {
        if (count($points) < 3) {
            return $points;
        }

        $start = $points[0];
        $end = $points[count($points) - 1];
        $maxDist = 0.0;
        $maxIdx = 0;

        for ($i = 1; $i < count($points) - 1; $i++) {
            $dist = $this->perpendicularDistance($points[$i], $start, $end);
            if ($dist > $maxDist) {
                $maxDist = $dist;
                $maxIdx = $i;
            }
        }

        if ($maxDist > $epsilon) {
            $left = $this->rdp(array_slice($points, 0, $maxIdx + 1), $epsilon);
            $right = $this->rdp(array_slice($points, $maxIdx), $epsilon);

            return array_merge(array_slice($left, 0, -1), $right);
        }

        return [$start, $end];
    }

    /** @param  array{0:float,1:float}  $point */
    /** @param  array{0:float,1:float}  $start */
    /** @param  array{0:float,1:float}  $end */
    private function perpendicularDistance(array $point, array $start, array $end): float
    {
        $dx = $end[0] - $start[0];
        $dy = $end[1] - $start[1];
        if ($dx == 0.0 && $dy == 0.0) {
            return sqrt(($point[0] - $start[0]) ** 2 + ($point[1] - $start[1]) ** 2);
        }
        $t = (($point[0] - $start[0]) * $dx + ($point[1] - $start[1]) * $dy) / ($dx * $dx + $dy * $dy);
        $t = max(0, min(1, $t));
        $projX = $start[0] + $t * $dx;
        $projY = $start[1] + $t * $dy;

        return sqrt(($point[0] - $projX) ** 2 + ($point[1] - $projY) ** 2);
    }

    /**
     * @param  list<array{0:float,1:float}>  $points
     * @return array{values:list<float>,estimated:bool}
     */
    private function fetchElevations(array $points): array
    {
        if ($points === []) {
            return ['values' => [], 'estimated' => true];
        }

        $results = [];
        $estimated = false;
        foreach (array_chunk($points, 100) as $batchIdx => $batch) {
            if ($batchIdx > 0) {
                usleep(1_100_000);
            }
            $locations = implode('|', array_map(fn ($p) => round($p[0], 4).','.round($p[1], 4), $batch));

            try {
                $response = Http::timeout(25)->get('https://api.opentopodata.org/v1/srtm30m', [
                    'locations' => $locations,
                ]);
                if ($response->successful()) {
                    foreach ($response->json('results') ?? [] as $r) {
                        $results[] = (float) ($r['elevation'] ?? 0);
                    }

                    continue;
                }
            } catch (\Throwable) {
                //
            }

            $estimated = true;
            foreach ($batch as $_) {
                $results[] = 1200.0;
            }
        }

        return ['values' => $results, 'estimated' => $estimated];
    }

    /** @return list<array{key:string,label:string,detail:string,kind:string}> */
    private function buildDataSources(bool $elevationEstimated): array
    {
        return [
            [
                'key' => 'route',
                'label' => 'مسیر جاده',
                'detail' => 'OpenStreetMap از طریق OSRM',
                'kind' => 'real',
            ],
            [
                'key' => 'elevation',
                'label' => 'ارتفاع زمین',
                'detail' => $elevationEstimated
                    ? 'دریافت نشد — نمودار ارتفاع تقریبی است'
                    : 'مدل ماهواره‌ای SRTM30m (OpenTopoData)',
                'kind' => $elevationEstimated ? 'fallback' : 'real',
            ],
            [
                'key' => 'weather',
                'label' => 'آب‌وهوا',
                'detail' => 'پیش‌بینی لحظه‌ای Open-Meteo',
                'kind' => 'real',
            ],
            [
                'key' => 'consumption',
                'label' => 'مصرف باتری',
                'detail' => 'مصرف پایه + سرعت/دما/شیب و باد در جهت حرکت — مسیر برگشت جدا محاسبه می‌شود',
                'kind' => 'estimated',
            ],
            [
                'key' => 'vehicle_catalog',
                'label' => 'مشخصات خودرو',
                'detail' => 'کاتالوگ دیتابیس SafirCharge (پنل ادمین)',
                'kind' => 'real',
            ],
        ];
    }

    /**
     * آب‌وهوا در ۳ نقطه (ابتدا، وسط، انتها) + درون‌یابی برای هر سگمنت.
     *
     * @param  list<array{0:float,1:float}>  $points
     * @return list<array{temperature_c:float,wind_speed_ms:float,wind_dir_deg:int}>
     */
    private function fetchWeatherAlongRoute(array $points): array
    {
        $n = count($points);
        if ($n === 0) {
            return [['temperature_c' => 20.0, 'wind_speed_ms' => 0.0, 'wind_dir_deg' => 0]];
        }

        $indices = array_unique([0, (int) floor($n / 2), max(0, $n - 1)]);
        $samples = [];
        foreach ($indices as $idx) {
            $samples[$idx] = $this->fetchWeather($points[$idx][0], $points[$idx][1]);
        }

        $out = [];
        for ($i = 0; $i < $n; $i++) {
            $out[] = $this->interpolateWeather($i, $n, $samples);
        }

        return $out;
    }

    /** @param  array<int, array{temperature_c:float,wind_speed_ms:float,wind_dir_deg:int}>  $samples */
    /** @return array{temperature_c:float,wind_speed_ms:float,wind_dir_deg:int} */
    private function interpolateWeather(int $index, int $total, array $samples): array
    {
        $keys = array_keys($samples);
        sort($keys);
        if ($index <= $keys[0]) {
            return $samples[$keys[0]];
        }
        $last = $keys[count($keys) - 1];
        if ($index >= $last) {
            return $samples[$last];
        }

        $lo = $keys[0];
        $hi = $last;
        foreach ($keys as $k) {
            if ($k <= $index) {
                $lo = $k;
            }
            if ($k >= $index) {
                $hi = $k;
                break;
            }
        }

        if ($lo === $hi) {
            return $samples[$lo];
        }

        $t = ($index - $lo) / ($hi - $lo);
        $a = $samples[$lo];
        $b = $samples[$hi];

        return [
            'temperature_c' => $a['temperature_c'] + ($b['temperature_c'] - $a['temperature_c']) * $t,
            'wind_speed_ms' => $a['wind_speed_ms'] + ($b['wind_speed_ms'] - $a['wind_speed_ms']) * $t,
            'wind_dir_deg' => (int) round($a['wind_dir_deg'] + ($b['wind_dir_deg'] - $a['wind_dir_deg']) * $t),
        ];
    }

    /** @return array{temperature_c:float,wind_speed_ms:float,wind_dir_deg:int} */
    private function fetchWeather(float $lat, float $lng): array
    {
        try {
            $response = Http::timeout(10)->get('https://api.open-meteo.com/v1/forecast', [
                'latitude' => $lat,
                'longitude' => $lng,
                'current' => 'temperature_2m,windspeed_10m,winddirection_10m',
                'windspeed_unit' => 'ms',
            ]);
            if ($response->successful()) {
                $c = $response->json('current') ?? [];

                return [
                    'temperature_c' => (float) ($c['temperature_2m'] ?? 20),
                    'wind_speed_ms' => (float) ($c['windspeed_10m'] ?? 0),
                    'wind_dir_deg' => (int) ($c['winddirection_10m'] ?? 0),
                ];
            }
        } catch (\Throwable) {
            //
        }

        return ['temperature_c' => 20.0, 'wind_speed_ms' => 0.0, 'wind_dir_deg' => 0];
    }

    /**
     * @param  list<array{0:float,1:float}>  $points
     * @param  list<float>  $elevations
     * @param  list<array{temperature_c:float,wind_speed_ms:float,wind_dir_deg:int}>  $weatherSamples
     * @return list<array<string, mixed>>
     */
    private function buildSegments(array $points, array $elevations, array $vehicle, array $weatherSamples, float $avgSpeedKmh): array
    {
        $segments = [];
        $basePerKm = (float) $vehicle['consumption_kwh100'] / 100;
        $massKg = (float) $vehicle['mass_kg'];
        $regenEff = (float) ($vehicle['regen_efficiency'] ?? 0.70);
        $cumKm = 0.0;

        for ($i = 1; $i < count($points); $i++) {
            $dKm = Geo::haversineKm($points[$i - 1][0], $points[$i - 1][1], $points[$i][0], $points[$i][1]);
            if ($dKm <= 0) {
                continue;
            }

            $cumKm += $dKm;
            $dh = $this->capElevationDelta(
                ($elevations[$i] ?? 0) - ($elevations[$i - 1] ?? 0),
            );
            $weather = $weatherSamples[$i] ?? $weatherSamples[0];

            $bearing = Geo::bearingDeg(
                $points[$i - 1][0],
                $points[$i - 1][1],
                $points[$i][0],
                $points[$i][1],
            );
            $eBase = $basePerKm * $dKm;
            $eBase *= $this->windConsumptionMultiplier(
                $weather['wind_speed_ms'],
                (float) $weather['wind_dir_deg'],
                $bearing,
            );

            $eGrav = $this->gravitationalEnergyKwh($dh, $massKg, $regenEff);
            if ($eGrav > 0) {
                $eGrav = min($eGrav, $eBase * 0.12);
            }

            $eNet = max(0, $eBase + $eGrav);
            $eBaseRef = $basePerKm * $dKm;
            $ratio = $eBaseRef > 0 ? $eNet / $eBaseRef : 1.0;

            $segments[] = [
                'from_km' => round($cumKm - $dKm, 2),
                'to_km' => round($cumKm, 2),
                'elevation_m' => round($elevations[$i] ?? 0),
                'delta_h_m' => round($dh, 1),
                'consumption_kwh' => round($eNet, 3),
                'ratio' => round($ratio, 2),
                'temp_c' => round($weather['temperature_c'], 1),
            ];
        }

        return $segments;
    }

    /** انرژی پتانسیل: سربالایی مصرف، سرازیری بازیابی (نه هر دو همزمان). */
    private function gravitationalEnergyKwh(float $deltaHm, float $massKg, float $regenEfficiency): float
    {
        if (abs($deltaHm) < 0.5) {
            return 0.0;
        }

        $kwh = ($massKg * 9.81 * abs($deltaHm)) / 3_600_000;

        if ($deltaHm > 0) {
            return $kwh / 0.92;
        }

        return -$kwh * min(0.80, max(0.55, $regenEfficiency));
    }

    private function windConsumptionMultiplier(float $windMs, float $windFromDeg, float $travelBearingDeg): float
    {
        if ($windMs < 0.5) {
            return 1.0;
        }

        $relativeRad = deg2rad($windFromDeg - $travelBearingDeg);
        $headwindMs = $windMs * cos($relativeRad);

        return 1.0 + min(0.04, max(-0.03, $headwindMs * 0.006));
    }

    private function capElevationDelta(float $deltaHm): float
    {
        return max(-30.0, min(30.0, $deltaHm));
    }

    /** @param  list<array<string, mixed>>  $segments */
    /** @return list<array<string, mixed>> */
    private function attachSocToSegments(array $segments, float $batteryKwh, int $startSoc): array
    {
        if ($batteryKwh <= 0) {
            return $segments;
        }

        $energy = $batteryKwh * ($startSoc / 100);
        $socBefore = (float) $startSoc;
        foreach ($segments as &$seg) {
            $seg['soc_before'] = round($socBefore, 1);
            $energy -= $seg['consumption_kwh'];
            $socAfter = max(0, ($energy / $batteryKwh) * 100);
            $seg['soc_after'] = round($socAfter, 1);
            $socBefore = $socAfter;
        }
        unset($seg);

        return $segments;
    }

    /** @param  list<array<string, mixed>>  $segments */
    /** @return list<array{km:float,soc:float}> */
    private function buildSocProfile(array $segments, int $startSoc): array
    {
        $profile = [['km' => 0, 'soc' => (float) $startSoc]];
        foreach ($segments as $seg) {
            $profile[] = ['km' => $seg['to_km'], 'soc' => $seg['soc_after']];
        }

        return $profile;
    }

    /** @param  list<array{0:float,1:float}>  $points */
    /** @param  list<float>  $elevations */
    /** @return list<array{km:float,elev_m:float}> */
    private function buildElevationProfile(array $points, array $elevations): array
    {
        $profile = [];
        $cumKm = 0.0;
        foreach ($points as $i => $pt) {
            if ($i > 0) {
                $cumKm += Geo::haversineKm($points[$i - 1][0], $points[$i - 1][1], $pt[0], $pt[1]);
            }
            $profile[] = ['km' => round($cumKm, 1), 'elev_m' => round($elevations[$i] ?? 0)];
        }

        return $profile;
    }

    /**
     * @param  list<array{0:float,1:float}>  $points
     * @return array{0:list<float>,1:list<array{0:float,1:float}>}
     */
    private function cumulativeKmAlongRoute(array $points): array
    {
        $cum = [0.0];
        for ($i = 1; $i < count($points); $i++) {
            $cum[$i] = $cum[$i - 1] + Geo::haversineKm(
                $points[$i - 1][0], $points[$i - 1][1],
                $points[$i][0], $points[$i][1],
            );
        }

        return [$cum, $points];
    }

    /** @param  list<array{0:float,1:float}>  $points */
    /** @return list<array<string, mixed>> */
    private function stationsAlongRoute(array $points): array
    {
        if ($points === []) {
            return [];
        }

        [$cumKm] = $this->cumulativeKmAlongRoute($points);
        $lats = array_column($points, 0);
        $lngs = array_column($points, 1);
        $pad = 0.15;

        $stations = Station::query()
            ->where('status', 'active')
            ->whereBetween('latitude', [min($lats) - $pad, max($lats) + $pad])
            ->whereBetween('longitude', [min($lngs) - $pad, max($lngs) + $pad])
            ->with(['chargers.connectors'])
            ->get();

        $result = [];
        foreach ($stations as $st) {
            if (! $st->latitude || ! $st->longitude) {
                continue;
            }

            $bestIdx = 0;
            $minDist = PHP_FLOAT_MAX;
            foreach ($points as $idx => $pt) {
                $d = Geo::haversineKm($pt[0], $pt[1], (float) $st->latitude, (float) $st->longitude);
                if ($d < $minDist) {
                    $minDist = $d;
                    $bestIdx = $idx;
                }
            }
            if ($minDist > 25) {
                continue;
            }

            $free = $st->chargers->flatMap(fn ($ch) => $ch->connectors)
                ->filter(fn ($c) => in_array($c->ocpp_status, ['Available', 'Preparing'], true))->count();

            $result[] = [
                'uuid' => $st->uuid,
                'code' => $st->code,
                'name' => $st->name,
                'lat' => (float) $st->latitude,
                'lng' => (float) $st->longitude,
                'route_km' => round($cumKm[$bestIdx], 1),
                'offset_km' => round($minDist, 1),
                'free_ports' => $free,
                'max_power_kw' => (int) $st->chargers->max('max_power_kw'),
                'url' => route('app.charger', ['station' => $st->code]),
            ];
        }

        usort($result, fn ($a, $b) => $a['route_km'] <=> $b['route_km']);

        return array_slice($result, 0, 15);
    }

    /**
     * شبیه‌سازی توقف‌های شارژ — segments دست‌نخورده می‌ماند (SOC هر بخش فقط از مصرف).
     *
     * @param  list<array<string, mixed>>  $segments
     * @param  list<array<string, mixed>>  $chargers
     * @return array{stops:list<array<string, mixed>>,arrival_soc:float,min_soc:float}
     */
    private function simulateChargePlan(array $segments, array $chargers, float $batteryKwh, int $startSoc, int $targetSoc): array
    {
        $empty = ['stops' => [], 'arrival_soc' => (float) $startSoc, 'min_soc' => (float) $startSoc];

        if ($batteryKwh <= 0 || $chargers === [] || $segments === []) {
            if ($segments !== []) {
                $minSoc = $startSoc;
                foreach ($segments as $seg) {
                    $minSoc = min($minSoc, (float) ($seg['soc_after'] ?? $startSoc));
                }
                $empty['arrival_soc'] = (float) end($segments)['soc_after'];
                $empty['min_soc'] = $minSoc;
            }

            return $empty;
        }

        $plan = [];
        $used = [];
        $chargeThreshold = max(15, min(35, $targetSoc));
        $energy = $batteryKwh * ($startSoc / 100);
        $chargerIdx = 0;
        $minSoc = (float) $startSoc;
        $socNow = (float) $startSoc;

        foreach ($segments as $seg) {
            $energy -= $seg['consumption_kwh'];
            $socNow = max(0, ($energy / $batteryKwh) * 100);
            $minSoc = min($minSoc, $socNow);

            if ($socNow < $chargeThreshold) {
                $charged = false;
                while ($chargerIdx < count($chargers)) {
                    $ch = $chargers[$chargerIdx];
                    if ($ch['route_km'] > $seg['to_km'] + 8) {
                        break;
                    }
                    $chargerIdx++;
                    if (isset($used[$ch['uuid']])) {
                        continue;
                    }
                    if ($ch['route_km'] < $seg['from_km'] - 15) {
                        continue;
                    }

                    $plan[] = [
                        'station_uuid' => $ch['uuid'],
                        'name' => $ch['name'],
                        'km' => $ch['route_km'],
                        'lat' => $ch['lat'],
                        'lng' => $ch['lng'],
                        'reason' => 'شارژ تا '.(int) $targetSoc.'٪',
                        'free_ports' => $ch['free_ports'],
                        'max_power_kw' => $ch['max_power_kw'],
                        'url' => $ch['url'],
                        'charge_to_soc' => $targetSoc,
                    ];
                    $used[$ch['uuid']] = true;
                    $energy = $batteryKwh * ($targetSoc / 100);
                    $socNow = (float) $targetSoc;
                    $minSoc = min($minSoc, $socNow);
                    $charged = true;
                    break;
                }

                if (! $charged && $plan === []) {
                    $ch = $chargers[0];
                    $plan[] = [
                        'station_uuid' => $ch['uuid'],
                        'name' => $ch['name'],
                        'km' => $ch['route_km'],
                        'lat' => $ch['lat'],
                        'lng' => $ch['lng'],
                        'reason' => 'شارژ پیشنهادی — سطح باتری بحرانی',
                        'free_ports' => $ch['free_ports'],
                        'max_power_kw' => $ch['max_power_kw'],
                        'url' => $ch['url'],
                        'charge_to_soc' => $targetSoc,
                    ];
                    $energy = $batteryKwh * ($targetSoc / 100);
                    $socNow = (float) $targetSoc;
                    $minSoc = min($minSoc, $socNow);
                }
            }
        }

        return [
            'stops' => $plan,
            'arrival_soc' => round($socNow, 1),
            'min_soc' => round($minSoc, 1),
        ];
    }

    /** @return array<string, mixed> */
    public function previewRoute(User $user, array $origin, array $destination, array $input = []): array
    {
        $vehicle = $this->resolveVehicle($user, $input);
        $soc = max(5, min(100, (int) ($input['current_soc'] ?? 80)));
        $targetSoc = max(10, min(100, (int) ($input['target_soc'] ?? 20)));
        $batteryKwh = (float) $vehicle['battery_kwh'];

        $route = $this->fetchOsrmRoute($origin, $destination);
        $points = $this->smartSampleRoute($route['coordinates'], 80);
        $geometry = $this->analyzeGeometry($points, $route, $vehicle, $batteryKwh, $soc, $targetSoc);
        $corridorChargers = $this->stationsAlongRoute($points);
        $chargerContext = $this->resolveRelevantChargers(
            $corridorChargers,
            $geometry['segments'],
            $vehicle,
            $batteryKwh,
            $soc,
            $targetSoc,
        );

        return [
            'route' => [
                'distance_km' => round($route['distance_m'] / 1000, 1),
                'duration_min' => (int) round($route['duration_s'] / 60),
                'direction' => $this->routeDirectionLabel($origin, $destination),
                'polyline' => array_map(fn ($p) => ['lat' => $p[0], 'lng' => $p[1]], $points),
            ],
            'chargers' => $chargerContext['display'],
            'charger_count' => count($chargerContext['display']),
            'corridor_charger_count' => count($corridorChargers),
            'needs_charge' => $chargerContext['needs_charge'],
            'stations_missing' => $chargerContext['stations_missing'],
            'estimated_charge_stops' => $chargerContext['estimated_charge_stops'],
            'range_km_on_start' => $chargerContext['range_km_on_start'],
            'battery_kwh' => $batteryKwh,
            'first_charge_km' => $chargerContext['first_charge_km'],
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $segments
     * @param  array<string, mixed>  $vehicle
     * @return array{
     *   range_km_on_start:float,
     *   first_charge_km:?float,
     *   estimated_charge_stops:int,
     *   consumption_per_100km:float,
     *   energy_from_charging_kwh:float
     * }
     */
    private function computeTripEnergyStats(
        array $segments,
        array $vehicle,
        float $batteryKwh,
        int $startSoc,
        int $targetSoc,
    ): array {
        $totalKm = $segments !== [] ? (float) end($segments)['to_km'] : 0.0;
        $totalKwh = array_sum(array_column($segments, 'consumption_kwh'));
        $kwhPerKm = $totalKm > 0
            ? $totalKwh / $totalKm
            : (float) $vehicle['consumption_kwh100'] / 100;

        $reserveSoc = max(10, $targetSoc);
        $chargeThreshold = max($reserveSoc, min(35, $targetSoc));
        $usableKwh = $batteryKwh * max(0, ($startSoc - $reserveSoc) / 100);

        $rangeFromSim = $this->kmWhereSocDropsBelow($segments, $batteryKwh, $startSoc, (float) $reserveSoc);
        $rangeKm = $rangeFromSim !== null
            ? round($rangeFromSim, 1)
            : ($kwhPerKm > 0 ? round($usableKwh / $kwhPerKm, 1) : 0.0);

        $firstFromSegments = $this->kmWhereSocDropsBelow($segments, $batteryKwh, $startSoc, (float) $chargeThreshold);
        $firstChargeKm = $firstFromSegments !== null
            ? round($firstFromSegments, 1)
            : ($rangeKm > 0 ? $rangeKm : null);

        $rechargeSoc = (int) min(90, max(70, $chargeThreshold + 40));
        $startKwh = $batteryKwh * ($startSoc / 100);
        $arrivalTargetKwh = $batteryKwh * ($targetSoc / 100);
        $energyFromCharging = max(0, $totalKwh - $startKwh + $arrivalTargetKwh);
        $kwhPerStop = $batteryKwh * max(0.01, ($rechargeSoc - $chargeThreshold) / 100);
        $stops = $energyFromCharging <= 0.01 ? 0 : (int) ceil($energyFromCharging / $kwhPerStop);

        if (! $this->tripNeedsCharge($segments, $startSoc, $targetSoc)) {
            $stops = 0;
        }

        return [
            'range_km_on_start' => $rangeKm,
            'first_charge_km' => $firstChargeKm,
            'estimated_charge_stops' => $stops,
            'consumption_per_100km' => round($kwhPerKm * 100, 1),
            'energy_from_charging_kwh' => round($energyFromCharging, 1),
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $corridorChargers
     * @param  list<array<string, mixed>>  $segments
     * @return array{
     *   needs_charge:bool,
     *   first_charge_km:?float,
     *   candidates:list<array<string, mixed>>,
     *   display:list<array<string, mixed>>
     * }
     */
    private function resolveRelevantChargers(
        array $corridorChargers,
        array $segments,
        array $vehicle,
        float $batteryKwh,
        int $startSoc,
        int $targetSoc,
    ): array {
        $energyStats = $this->computeTripEnergyStats($segments, $vehicle, $batteryKwh, $startSoc, $targetSoc);

        $noChargeNeeded = [
            'needs_charge' => false,
            'stations_missing' => false,
            'first_charge_km' => null,
            'estimated_charge_stops' => 0,
            'range_km_on_start' => $energyStats['range_km_on_start'],
            'consumption_per_100km' => $energyStats['consumption_per_100km'],
            'candidates' => [],
            'display' => [],
        ];

        if ($segments === []) {
            return $noChargeNeeded;
        }

        if (! $this->tripNeedsCharge($segments, $startSoc, $targetSoc)) {
            return $noChargeNeeded;
        }

        if ($corridorChargers === []) {
            return [
                'needs_charge' => true,
                'stations_missing' => true,
                'first_charge_km' => $energyStats['first_charge_km'],
                'estimated_charge_stops' => $energyStats['estimated_charge_stops'],
                'range_km_on_start' => $energyStats['range_km_on_start'],
                'consumption_per_100km' => $energyStats['consumption_per_100km'],
                'candidates' => [],
                'display' => [],
            ];
        }

        $candidates = $this->filterChargersInChargeWindows(
            $corridorChargers,
            $segments,
            $batteryKwh,
            $startSoc,
            $targetSoc,
        );
        $simulation = $this->simulateChargePlan($segments, $candidates, $batteryKwh, $startSoc, $targetSoc);
        $byUuid = [];
        foreach ($candidates as $ch) {
            $byUuid[$ch['uuid']] = $ch;
        }

        $display = [];
        foreach ($simulation['stops'] as $stop) {
            $ch = $byUuid[$stop['station_uuid'] ?? ''] ?? null;
            if ($ch) {
                $display[] = $ch;
            }
        }

        if ($display === [] && $candidates !== []) {
            $display = array_slice($candidates, 0, 3);
        }

        $firstChargeKm = $energyStats['first_charge_km'];
        if ($firstChargeKm === null && $display !== []) {
            $firstChargeKm = (float) $display[0]['route_km'];
        }

        return [
            'needs_charge' => true,
            'stations_missing' => $display === [],
            'first_charge_km' => $firstChargeKm,
            'estimated_charge_stops' => $energyStats['estimated_charge_stops'],
            'range_km_on_start' => $energyStats['range_km_on_start'],
            'consumption_per_100km' => $energyStats['consumption_per_100km'],
            'candidates' => $candidates,
            'display' => $display,
        ];
    }

    /** @param  list<array<string, mixed>>  $segments */
    private function tripNeedsCharge(array $segments, int $startSoc, int $targetSoc): bool
    {
        if ($segments === []) {
            return false;
        }

        $arrivalSoc = (float) end($segments)['soc_after'];
        $minSoc = (float) $startSoc;
        $reserveSoc = max(10, $targetSoc);

        foreach ($segments as $seg) {
            $minSoc = min($minSoc, (float) ($seg['soc_after'] ?? $startSoc));
        }

        return $arrivalSoc < $reserveSoc || $minSoc < $reserveSoc;
    }

    /**
     * @param  list<array<string, mixed>>  $allChargers
     * @param  list<array<string, mixed>>  $segments
     * @return list<array<string, mixed>>
     */
    private function filterChargersInChargeWindows(
        array $allChargers,
        array $segments,
        float $batteryKwh,
        int $startSoc,
        int $targetSoc,
    ): array {
        $chargeThreshold = max(15, min(35, $targetSoc));
        $totalKm = (float) end($segments)['to_km'];
        $picked = [];
        $resumeFromKm = 0.0;
        $maxPasses = 8;

        for ($pass = 0; $pass < $maxPasses; $pass++) {
            $energy = $batteryKwh * (($pass === 0 ? $startSoc : $targetSoc) / 100);
            $chargedThisPass = false;

            foreach ($segments as $seg) {
                if ((float) $seg['to_km'] <= $resumeFromKm) {
                    continue;
                }

                $consume = (float) $seg['consumption_kwh'];
                $segLen = max(0.01, (float) $seg['to_km'] - (float) $seg['from_km']);
                if ((float) $seg['from_km'] < $resumeFromKm) {
                    $consume *= max(0, ((float) $seg['to_km'] - $resumeFromKm) / $segLen);
                }

                $energy -= $consume;
                $socNow = max(0, ($energy / $batteryKwh) * 100);
                if ($socNow >= $chargeThreshold) {
                    continue;
                }

                $winFrom = max($resumeFromKm, (float) $seg['from_km'] - 20);
                $winTo = min($totalKm, (float) $seg['to_km'] + 60);

                foreach ($allChargers as $ch) {
                    if ($ch['route_km'] >= $winFrom && $ch['route_km'] <= $winTo) {
                        $picked[$ch['uuid']] = $ch;
                    }
                }

                $next = null;
                foreach ($allChargers as $ch) {
                    if ($ch['route_km'] >= max($resumeFromKm, (float) $seg['from_km']) && $ch['route_km'] <= $winTo) {
                        $next = $ch;
                        break;
                    }
                }

                if ($next) {
                    $picked[$next['uuid']] = $next;
                    $resumeFromKm = (float) $next['route_km'];
                    $chargedThisPass = true;
                }

                break;
            }

            if (! $chargedThisPass) {
                break;
            }
        }

        $result = array_values($picked);
        usort($result, fn ($a, $b) => $a['route_km'] <=> $b['route_km']);

        return $result;
    }

    /** @param  list<array<string, mixed>>  $segments */
    private function kmWhereSocDropsBelow(
        array $segments,
        float $batteryKwh,
        int $startSoc,
        float $threshold,
    ): ?float {
        $energy = $batteryKwh * ($startSoc / 100);

        foreach ($segments as $seg) {
            $energy -= $seg['consumption_kwh'];
            $socNow = max(0, ($energy / $batteryKwh) * 100);
            if ($socNow < $threshold) {
                return (float) $seg['from_km'];
            }
        }

        return null;
    }

    /**
     * @param  list<array<string, mixed>>  $chargers
     * @param  list<array<string, mixed>>  $chargePlan
     * @param  list<array<string, mixed>>  $segments
     * @return array<string, mixed>
     */
    private function buildVerdict(
        float $arrivalSocDirect,
        float $arrivalSocPlanned,
        int $targetSoc,
        int $startSoc,
        array $chargers,
        array $chargePlan,
        array $segments,
        int $corridorChargerCount = 0,
        ?float $firstChargeKm = null,
        int $estimatedChargeStops = 0,
        float $batteryKwh = 60.0,
        ?float $rangeKmOnStart = null,
    ): array {
        $minSoc = $arrivalSocDirect;
        foreach ($segments as $seg) {
            $minSoc = min($minSoc, (float) ($seg['soc_after'] ?? $startSoc));
        }

        $chargerCount = count($chargers);
        $reserveSoc = max(10, $targetSoc);
        $canReachDirect = $arrivalSocDirect >= $reserveSoc && $minSoc >= $reserveSoc;
        $canReachWithPlan = count($chargePlan) > 0 && $arrivalSocPlanned >= $targetSoc;
        $needsCharge = ! $canReachDirect;
        $warnings = [];
        $usableKwh = $batteryKwh * max(0, ($startSoc - $reserveSoc) / 100);
        $rangeHint = $rangeKmOnStart !== null && $rangeKmOnStart > 0
            ? 'باتری '.round($batteryKwh, 0).' کیلووات‌ساعت؛ با شارژ '.$startSoc.'٪ و رزرو '.$reserveSoc.'٪ انرژی در دسترس '.round($usableKwh, 0).' کیلووات‌ساعت — برد با همین مصرف مسیر حدود '.round($rangeKmOnStart, 0).' کیلومتر. '
            : '';
        $stopsHint = '';
        if ($estimatedChargeStops > 1) {
            $stopsHint = 'برای کل مسیر حدود '.$estimatedChargeStops.' توقف شارژ (هر بار تا حدود ۷۰٪) لازم است. ';
        } elseif ($estimatedChargeStops === 1) {
            $stopsHint = 'حداقل یک توقف شارژ در مسیر لازم است. ';
        }

        if ($needsCharge && $chargerCount === 0) {
            if ($corridorChargerCount > 0) {
                $warnings[] = $corridorChargerCount.' ایستگاه نزدیک مسیر است ولی در محدوده‌ای که شارژ کم می‌شود قرار ندارد.';
            } else {
                $warnings[] = 'هیچ ایستگاه شارژ SafirCharge در این مسیر ثبت نشده است.';
            }
        }

        if ($minSoc < 5) {
            $warnings[] = 'بدون توقف شارژ، باتری در میانه مسیر تقریباً خالی می‌شود.';
        } elseif ($minSoc < 15) {
            $warnings[] = 'بدون توقف شارژ، سطح باتری به حد بحرانی نزدیک می‌شود.';
        }

        if ($needsCharge && $chargerCount === 0) {
            $kmHint = $firstChargeKm !== null
                ? ' اولین نیاز به شارژ حدود کیلومتر '.round($firstChargeKm, 0).' است.'
                : '';
            $corridorHint = $corridorChargerCount > 0
                ? ' '.$corridorChargerCount.' ایستگاه فقط در بخش‌های دیگر مسیر هستند و در محل نیاز شما نیستند.'
                : ' هیچ ایستگاه SafirCharge در این مسیر پوشش داده نشده است.';

            return [
                'status' => 'no_chargers',
                'title' => 'به مقصد نمی‌رسید — ایستگاه شارژ در محل نیاز نیست',
                'message' => $rangeHint.$stopsHint.'بدون توقف به مقصد نمی‌رسید (شارژ پایان حدود '.round($arrivalSocDirect, 0).'٪).'.$kmHint.$corridorHint,
                'can_reach_destination' => false,
                'can_reach_with_plan' => false,
                'needs_charge' => true,
                'min_soc_on_route' => round($minSoc, 1),
                'warnings' => $warnings,
            ];
        }

        if ($needsCharge && count($chargePlan) > 0 && $canReachWithPlan) {
            return [
                'status' => 'needs_charge',
                'title' => 'مسیر از ایستگاه‌های شارژ عبور می‌کند',
                'message' => 'مستقیم به مقصد نمی‌رسید (پایان حدود '.round($arrivalSocDirect, 0).'٪). مسیر روی نقشه از '.count($chargePlan).' ایستگاه شارژ می‌گذرد و با توقف به حدود '.round($arrivalSocPlanned, 0).'٪ می‌رسید.',
                'can_reach_destination' => false,
                'can_reach_with_plan' => true,
                'needs_charge' => true,
                'min_soc_on_route' => round($minSoc, 1),
                'warnings' => $warnings,
            ];
        }

        if ($needsCharge && count($chargePlan) > 0) {
            return [
                'status' => 'needs_charge',
                'title' => 'نیاز به توقف برای شارژ دارید',
                'message' => 'بدون توقف به مقصد نمی‌رسید (پایان حدود '.round($arrivalSocDirect, 0).'٪). '.count($chargePlan).' توقف شارژ پیشنهاد شده.',
                'can_reach_destination' => false,
                'can_reach_with_plan' => $canReachWithPlan,
                'needs_charge' => true,
                'min_soc_on_route' => round($minSoc, 1),
                'warnings' => $warnings,
            ];
        }

        if ($needsCharge && $chargerCount > 0) {
            return [
                'status' => 'needs_charge',
                'title' => 'شارژ پایان مسیر از هدف کمتر است',
                'message' => 'به مقصد می‌رسید ولی شارژ پایان (حدود '.round($arrivalSocDirect, 0).'٪) از هدف '.$targetSoc.'٪ کمتر است.',
                'can_reach_destination' => $arrivalSocDirect >= $targetSoc,
                'can_reach_with_plan' => false,
                'needs_charge' => true,
                'min_soc_on_route' => round($minSoc, 1),
                'warnings' => $warnings,
            ];
        }

        return [
            'status' => 'ok',
            'title' => 'با شارژ فعلی به مقصد می‌رسید',
            'message' => 'بدون توقف اضافه به مقصد می‌رسید (شارژ حدود '.round($arrivalSocDirect, 0).'٪ در پایان مسیر).',
            'can_reach_destination' => true,
            'can_reach_with_plan' => true,
            'needs_charge' => false,
            'min_soc_on_route' => round($minSoc, 1),
            'warnings' => $warnings,
        ];
    }

    /** @param  array{lat:float,lng:float}  $origin */
    /** @param  array{lat:float,lng:float}  $destination */
    private function routeDirectionLabel(array $origin, array $destination): string
    {
        $dLat = (float) $destination['lat'] - (float) $origin['lat'];
        $dLng = (float) $destination['lng'] - (float) $origin['lng'];
        if (abs($dLat) < 0.05 && abs($dLng) < 0.05) {
            return 'مسیر کوتاه';
        }

        $angle = rad2deg(atan2($dLng, $dLat));
        if ($angle < 0) {
            $angle += 360;
        }

        return match (true) {
            $angle >= 337.5 || $angle < 22.5 => 'مسیر به سمت شمال',
            $angle < 67.5 => 'مسیر به سمت شمال‌شرق',
            $angle < 112.5 => 'مسیر به سمت شرق',
            $angle < 157.5 => 'مسیر به سمت جنوب‌شرق',
            $angle < 202.5 => 'مسیر به سمت جنوب',
            $angle < 247.5 => 'مسیر به سمت جنوب‌غرب',
            $angle < 292.5 => 'مسیر به سمت غرب',
            default => 'مسیر به سمت شمال‌غرب',
        };
    }

    /** @return array<string, mixed> */
    public function pageData(User $user): array
    {
        $vehicles = $user->vehicles()->orderByDesc('is_default')->get();
        $defaultVehicle = $vehicles->firstWhere('is_default', true) ?? $vehicles->first();

        return [
            'vehiclePresets' => EvVehicleCatalog::presetsForNavigator(),
            'userVehicles' => $vehicles,
            'defaultVehicle' => $defaultVehicle,
            'mapCenter' => Geo::defaultRef(),
            'apiConfigured' => config('safircharge.ev_navigator.api_url') !== '',
            'popularRoutes' => config('safircharge.ev_navigator.popular_routes', []),
        ];
    }
}
