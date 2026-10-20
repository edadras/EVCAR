<?php

namespace App\Services\Navigator;

/**
 * برنامه‌ریز توقف شارژ در مسیر.
 *
 * الگوریتم حریصانهٔ «دورترین ایستگاه قابل‌دسترس»: در هر مرحله اگر با انرژی فعلی و
 * حفظ رزرو به مقصد می‌رسیم توقف نمی‌کنیم؛ وگرنه به دورترین ایستگاهی می‌رویم که با
 * حداقل حاشیهٔ ایمنی به آن می‌رسیم و فقط به اندازهٔ لازم (حداکثر تا chargeToSoc) شارژ می‌کنیم.
 * انرژی انحراف از مسیر تا ایستگاه (رفت و برگشت) هم لحاظ می‌شود.
 */
class ChargePlanner
{
    /** حداقل SOC هنگام رسیدن به ایستگاه. */
    public const ARRIVAL_BUFFER_SOC = 8;

    private const MAX_STOPS = 12;

    /** @var list<float> */
    private array $kmPoints = [];

    /** @var list<float> */
    private array $energyPoints = [];

    private float $kwhPerKm = 0.16;

    /**
     * @param  list<array<string, mixed>>  $segments  هر بخش: from_km, to_km, consumption_kwh
     * @param  list<array<string, mixed>>  $chargers  ایستگاه‌های نزدیک مسیر: uuid, route_km, offset_km, max_power_kw, free_ports, ...
     * @return array{
     *   needs_charge: bool, reachable: bool, stops: list<array<string, mixed>>,
     *   arrival_soc: float, direct_arrival_soc: float, min_soc: float,
     *   stranded_km: ?float, first_charge_km: ?float,
     *   total_charge_minutes: int, total_charge_kwh: float, total_charge_cost: float
     * }
     */
    public function plan(
        array $segments,
        array $chargers,
        float $batteryKwh,
        int $startSoc,
        int $reserveSoc,
        int $chargeToSoc = 80,
        float $vehicleMaxKw = 100,
        float $pricePerKwh = 0,
        float $sessionFee = 0,
    ): array {
        $this->buildEnergyProfile($segments);
        $totalKm = $this->kmPoints === [] ? 0.0 : end($this->kmPoints);
        $reserveSoc = max(5, min(90, $reserveSoc));
        $chargeToSoc = max($reserveSoc + 10, min(100, $chargeToSoc));

        $energy = $batteryKwh * $startSoc / 100;
        $directArrival = $batteryKwh > 0 ? ($energy - $this->energyBetween(0, $totalKm)) / $batteryKwh * 100 : 0;
        $result = [
            'needs_charge' => $directArrival < $reserveSoc,
            'reachable' => $directArrival >= $reserveSoc,
            'stops' => [],
            'arrival_soc' => round(max(0, $directArrival), 1),
            'direct_arrival_soc' => round(max(0, $directArrival), 1),
            'min_soc' => round(max(0, min($startSoc, $directArrival)), 1),
            'stranded_km' => null,
            'first_charge_km' => null,
            'total_charge_minutes' => 0,
            'total_charge_kwh' => 0.0,
            'total_charge_cost' => 0.0,
        ];

        if ($batteryKwh <= 0 || $totalKm <= 0 || ! $result['needs_charge']) {
            return $result;
        }

        usort($chargers, fn ($a, $b) => $a['route_km'] <=> $b['route_km']);
        $pos = 0.0;
        $minSoc = (float) $startSoc;
        $used = [];

        for ($i = 0; $i < self::MAX_STOPS; $i++) {
            $arrivalKwh = $energy - $this->energyBetween($pos, $totalKm);
            if ($arrivalKwh >= $batteryKwh * $reserveSoc / 100) {
                $result['reachable'] = true;
                $result['arrival_soc'] = round($arrivalKwh / $batteryKwh * 100, 1);
                $minSoc = min($minSoc, $result['arrival_soc']);
                break;
            }

            $best = null;
            $bestArrive = 0.0;
            foreach ($chargers as $ch) {
                $km = (float) $ch['route_km'];
                if ($km <= $pos + 1 || $km >= $totalKm || isset($used[$ch['uuid']])) {
                    continue;
                }
                $arrive = $energy - $this->energyBetween($pos, $km) - $this->detourKwh($ch);
                if ($arrive < $batteryKwh * self::ARRIVAL_BUFFER_SOC / 100) {
                    continue;
                }
                // دورترین؛ در فاصلهٔ برابر (±۵ کیلومتر) ایستگاه پرقدرت‌تر.
                if ($best === null || $km > (float) $best['route_km'] + 5
                    || (abs($km - (float) $best['route_km']) <= 5 && (int) $ch['max_power_kw'] > (int) $best['max_power_kw'])) {
                    $best = $ch;
                    $bestArrive = $arrive;
                }
            }

            if ($best === null) {
                $result['reachable'] = false;
                $result['stranded_km'] = round($this->kmWhereEnergyLeft($pos, $energy, $batteryKwh * self::ARRIVAL_BUFFER_SOC / 100), 1);
                $result['arrival_soc'] = round(max(0, $arrivalKwh / $batteryKwh * 100), 1);
                $minSoc = min($minSoc, max(0, $result['arrival_soc']));
                break;
            }

            $km = (float) $best['route_km'];
            $detour = $this->detourKwh($best);
            $needToFinish = $this->energyBetween($km, $totalKm) + $detour + $batteryKwh * $reserveSoc / 100;
            $departKwh = min($batteryKwh * $chargeToSoc / 100, $needToFinish + $batteryKwh * 0.03);
            $departSoc = min($chargeToSoc, (int) (ceil($departKwh / $batteryKwh * 100 / 5) * 5));
            $departSoc = max($departSoc, (int) ceil($bestArrive / $batteryKwh * 100));
            if ($departSoc - $bestArrive / $batteryKwh * 100 < 5) {
                // بدون شارژ تا بالاتر از chargeToSoc پیشرفتی ممکن نیست؛ شارژ کامل یا توقف بی‌فایده.
                if ($bestArrive / $batteryKwh * 100 > 95) {
                    $result['reachable'] = false;
                    $result['stranded_km'] = round($this->kmWhereEnergyLeft($pos, $energy, $batteryKwh * self::ARRIVAL_BUFFER_SOC / 100), 1);
                    break;
                }
                $departSoc = 100;
            }
            $departKwh = $batteryKwh * $departSoc / 100;

            $arriveSoc = $bestArrive / $batteryKwh * 100;
            $chargedKwh = max(0, $departKwh - $bestArrive);
            $minutes = $this->chargeMinutes($arriveSoc, $departSoc, $batteryKwh, min((float) ($best['max_power_kw'] ?: 22), $vehicleMaxKw));
            $cost = $chargedKwh * $pricePerKwh + $sessionFee;
            $minSoc = min($minSoc, $arriveSoc);

            $result['stops'][] = array_merge($best, [
                'station_uuid' => $best['uuid'],
                'km' => round($km, 1),
                'arrive_soc' => round($arriveSoc, 0),
                'depart_soc' => $departSoc,
                'charge_to_soc' => $departSoc,
                'energy_kwh' => round($chargedKwh, 1),
                'duration_min' => $minutes,
                'cost_rial' => round($cost),
                'reason' => 'رسیدن با '.(int) round($arriveSoc).'٪ — شارژ تا '.$departSoc.'٪ (حدود '.$minutes.' دقیقه)',
            ]);
            $result['total_charge_minutes'] += $minutes;
            $result['total_charge_kwh'] += $chargedKwh;
            $result['total_charge_cost'] += $cost;

            $used[$best['uuid']] = true;
            $pos = $km;
            $energy = $departKwh - $detour;
        }

        $result['first_charge_km'] = $result['stops'][0]['km'] ?? $result['stranded_km'];
        $result['min_soc'] = round(max(0, $minSoc), 1);
        $result['total_charge_kwh'] = round($result['total_charge_kwh'], 1);
        $result['total_charge_cost'] = round($result['total_charge_cost']);

        return $result;
    }

    /**
     * زمان شارژ با مدل ساده: توان کامل تا ۸۰٪ و حدود ۳۵٪ توان بالای ۸۰٪.
     */
    public function chargeMinutes(float $fromSoc, float $toSoc, float $batteryKwh, float $powerKw): int
    {
        if ($toSoc <= $fromSoc || $powerKw <= 0) {
            return 0;
        }

        $fast = max(0, min($toSoc, 80) - $fromSoc) / 100 * $batteryKwh;
        $slow = max(0, $toSoc - max($fromSoc, 80)) / 100 * $batteryKwh;
        $hours = $fast / ($powerKw * 0.9) + $slow / ($powerKw * 0.35);

        return max(1, (int) ceil($hours * 60));
    }

    /** @param list<array<string, mixed>> $segments */
    private function buildEnergyProfile(array $segments): void
    {
        $this->kmPoints = [0.0];
        $this->energyPoints = [0.0];
        $energy = 0.0;

        foreach ($segments as $seg) {
            // انرژی بازیابی‌شده در سرازیری می‌تواند منفی باشد؛ جمع تجمعی آن را لحاظ می‌کند.
            $energy += (float) $seg['consumption_kwh'];
            $this->kmPoints[] = (float) $seg['to_km'];
            $this->energyPoints[] = $energy;
        }

        $totalKm = end($this->kmPoints);
        $this->kwhPerKm = $totalKm > 0 ? max(0.08, $energy / $totalKm) : 0.16;
    }

    private function energyAt(float $km): float
    {
        $n = count($this->kmPoints);
        if ($km <= 0 || $n < 2) {
            return 0.0;
        }

        for ($i = 1; $i < $n; $i++) {
            if ($km <= $this->kmPoints[$i]) {
                $span = max(1e-6, $this->kmPoints[$i] - $this->kmPoints[$i - 1]);
                $t = ($km - $this->kmPoints[$i - 1]) / $span;

                return $this->energyPoints[$i - 1] + $t * ($this->energyPoints[$i] - $this->energyPoints[$i - 1]);
            }
        }

        return end($this->energyPoints);
    }

    private function energyBetween(float $fromKm, float $toKm): float
    {
        return $this->energyAt($toKm) - $this->energyAt($fromKm);
    }

    /** @param array<string, mixed> $charger */
    private function detourKwh(array $charger): float
    {
        return (float) ($charger['offset_km'] ?? 0) * 1.3 * $this->kwhPerKm;
    }

    private function kmWhereEnergyLeft(float $fromKm, float $energy, float $floorKwh): float
    {
        $budget = $energy - $floorKwh;
        $start = $this->energyAt($fromKm);
        $n = count($this->kmPoints);

        for ($i = 1; $i < $n; $i++) {
            if ($this->kmPoints[$i] <= $fromKm) {
                continue;
            }
            if ($budget < $this->energyPoints[$i] - $start) {
                $prevKm = max($fromKm, $this->kmPoints[$i - 1]);
                $prevE = $this->energyAt($prevKm) - $start;
                $segE = max(1e-6, ($this->energyPoints[$i] - $start) - $prevE);

                return $prevKm + ($budget - $prevE) / $segE * ($this->kmPoints[$i] - $prevKm);
            }
        }

        return end($this->kmPoints);
    }
}
