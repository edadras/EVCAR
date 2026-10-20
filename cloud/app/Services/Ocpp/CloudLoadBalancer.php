<?php

namespace App\Services\Ocpp;

use App\Models\ChargingSession;
use App\Models\Station;

/**
 * Smart Charging برای ایستگاه‌های متصل مستقیم: سقف توان انشعاب (station.meta.grid_limit_kw)
 * بین جلسه‌های فعال تقسیم و با SetChargingProfile (TxProfile) اعمال می‌شود.
 */
class CloudLoadBalancer
{
    public const MIN_KW = 4.0;

    public function __construct(private CloudOcppCommander $commander) {}

    public function apply(?int $stationId = null): int
    {
        $sent = 0;
        $stations = Station::query()
            ->when($stationId, fn ($q) => $q->whereKey($stationId))
            ->whereNotNull('meta')
            ->get()
            ->filter(fn (Station $s) => (float) ($s->meta['grid_limit_kw'] ?? 0) > 0);

        foreach ($stations as $station) {
            $sessions = ChargingSession::with(['charger', 'connector'])
                ->where('station_id', $station->id)
                ->where('status', 'active')
                ->whereNotNull('ocpp_transaction_id')
                ->get()
                ->filter(fn (ChargingSession $s) => (bool) ($s->meta['direct_ocpp'] ?? false));
            if ($sessions->isEmpty()) {
                continue;
            }

            $caps = $sessions->mapWithKeys(fn ($s) => [$s->id => (float) ($s->connector?->max_power_kw ?: $s->charger?->max_power_kw ?: 22)])->all();
            foreach (self::share((float) $station->meta['grid_limit_kw'], $caps) as $id => $kw) {
                $session = $sessions->firstWhere('id', $id);
                if (abs((float) ($session->meta['power_limit_kw'] ?? -1) - $kw) < 0.5) {
                    continue;
                }
                $number = (int) $session->connector->connector_number;
                $profile = OcppCommandMap::chargingProfile($number, $kw, 'TxProfile', 100 + $number, 1);
                $profile['csChargingProfiles']['transactionId'] = (int) $session->ocpp_transaction_id;
                $this->commander->dispatch($session->charger->charge_point_id, 'SetChargingProfile', $profile);
                $session->update(['meta' => array_merge($session->meta ?? [], ['power_limit_kw' => $kw])]);
                $sent++;
            }
        }

        return $sent;
    }

    /**
     * @param  array<int, float>  $caps
     * @return array<int, float>
     */
    public static function share(float $limit, array $caps): array
    {
        $result = [];
        $remaining = $limit;
        $open = $caps;
        while ($open !== []) {
            $each = $remaining / count($open);
            $capped = array_filter($open, fn ($cap) => $cap <= $each);
            if ($capped === []) {
                foreach ($open as $id => $_) {
                    $result[$id] = round(max(self::MIN_KW, $each), 1);
                }
                break;
            }
            foreach ($capped as $id => $cap) {
                $result[$id] = round($cap, 1);
                $remaining -= $cap;
                unset($open[$id]);
            }
        }

        return $result;
    }
}
