<?php

namespace App\Services\Ocpp;

/**
 * تبدیل یک MeterValue (OCPP 1.6) به مقادیر قابل استفاده.
 * پیش‌فرض‌های استاندارد: measurand = Energy.Active.Import.Register، unit = Wh.
 */
final class MeterValueParser
{
    /**
     * @param  array<string, mixed>  $meterValue
     * @return array{energy_wh?:int,power_w?:float,voltage_v?:float,current_a?:float,soc?:int}|null
     */
    public static function parse(array $meterValue): ?array
    {
        $samples = $meterValue['sampledValue'] ?? [];
        if (! is_array($samples)) {
            return null;
        }

        $energy = null;
        $energyPhases = [];
        $powerTotal = null;
        $powerPhases = [];
        $voltages = [];
        $currents = [];
        $soc = null;

        foreach ($samples as $sample) {
            if (! is_array($sample) || ! isset($sample['value'])) {
                continue;
            }
            if (($sample['format'] ?? 'Raw') === 'SignedData' || ! is_numeric($sample['value'])) {
                continue;
            }

            $measurand = (string) ($sample['measurand'] ?? 'Energy.Active.Import.Register');
            $unit = (string) ($sample['unit'] ?? '');
            $value = (float) $sample['value'];
            $phase = (string) ($sample['phase'] ?? '');
            $location = (string) ($sample['location'] ?? 'Outlet');

            if ($location === 'EV' && $measurand !== 'SoC') {
                continue;
            }

            switch ($measurand) {
                case 'Energy.Active.Import.Register':
                    if ($phase === '') {
                        $energy = self::toWh($value, $unit);
                    } else {
                        $energyPhases[$phase] = self::toWh($value, $unit);
                    }
                    break;
                case 'Power.Active.Import':
                    $watts = self::toW($value, $unit);
                    if ($phase === '') {
                        $powerTotal = $watts;
                    } else {
                        $powerPhases[$phase] = $watts;
                    }
                    break;
                case 'Voltage':
                    // ولتاژ فاز-به-فاز (L1-L2 ...) با ولتاژ فاز-نول میانگین گرفته نمی‌شود.
                    if (! in_array($phase, ['L1-L2', 'L2-L3', 'L3-L1'], true)) {
                        $voltages[] = $value;
                    }
                    break;
                case 'Current.Import':
                    $currents[$phase ?: 'total'] = $value;
                    break;
                case 'SoC':
                    $soc = (int) round($value);
                    break;
            }
        }

        $result = [];
        if ($energy === null && $energyPhases !== []) {
            $energy = array_sum($energyPhases);
        }
        if ($energy !== null) {
            $result['energy_wh'] = (int) round($energy);
        }
        if ($powerTotal !== null) {
            $result['power_w'] = $powerTotal;
        } elseif ($powerPhases !== []) {
            $result['power_w'] = array_sum($powerPhases);
        }
        if ($voltages !== []) {
            $result['voltage_v'] = array_sum($voltages) / count($voltages);
        }
        if ($currents !== []) {
            $result['current_a'] = $currents['total'] ?? max($currents);
        }
        if ($soc !== null) {
            $result['soc'] = $soc;
        }

        return $result === [] ? null : $result;
    }

    private static function toWh(float $value, string $unit): float
    {
        return strtolower($unit) === 'kwh' ? $value * 1000 : $value;
    }

    private static function toW(float $value, string $unit): float
    {
        return strtolower($unit) === 'kw' ? $value * 1000 : $value;
    }
}
