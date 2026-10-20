<?php

namespace App\Services\Ocpp;

use App\Models\EdgeChargingSession;
use App\Models\StationIdentity;

/**
 * Smart Charging: تقسیم سقف توان انشعاب ایستگاه بین جلسه‌های فعال با SetChargingProfile (TxProfile).
 * سقف در تنظیمات ایستگاه ابر (meta.grid_limit_kw) تعریف و به Edge سینک می‌شود.
 */
class LoadBalancer
{
    /** کمترین توان قابل‌قبول برای هر خودرو (حدود ۶ آمپر سه‌فاز). */
    public const MIN_KW = 4.0;

    public function __construct(private ChargePointCommander $commander) {}

    public function limitKw(): ?float
    {
        $limit = StationIdentity::first()?->meta['grid_limit_kw'] ?? null;

        return $limit !== null && (float) $limit > 0 ? (float) $limit : null;
    }

    /**
     * @return array<int, float> sessionId => kW
     */
    public function allocate(): array
    {
        $limit = $this->limitKw();
        if ($limit === null) {
            return [];
        }

        $sessions = EdgeChargingSession::with(['charger', 'connector'])
            ->where('status', 'active')
            ->whereNotNull('ocpp_transaction_id')
            ->get();
        if ($sessions->isEmpty()) {
            return [];
        }

        $caps = $sessions->mapWithKeys(fn ($s) => [$s->id => (float) ($s->connector?->max_power_kw ?: $s->charger?->max_power_kw ?: 22)])->all();

        return self::share($limit, $caps);
    }

    /**
     * تقسیم عادلانه (water-filling): هر جلسه حداکثر تا توان کانکتورش؛ مازاد بین بقیه تقسیم می‌شود.
     *
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

    /** ارسال پروفایل فقط برای جلسه‌هایی که سهمشان تغییر کرده است. */
    public function apply(): int
    {
        $sent = 0;
        foreach ($this->allocate() as $sessionId => $kw) {
            $session = EdgeChargingSession::with('charger', 'connector')->find($sessionId);
            if (! $session || abs((float) ($session->meta['power_limit_kw'] ?? -1) - $kw) < 0.5) {
                continue;
            }

            $profile = OcppCommandMap::chargingProfile((int) $session->connector->connector_number, $kw, 'TxProfile', 100 + (int) $session->connector->connector_number, 1);
            $profile['csChargingProfiles']['transactionId'] = (int) $session->ocpp_transaction_id;
            $this->commander->dispatch($session->charger->charge_point_id, 'SetChargingProfile', $profile);
            $session->update(['meta' => array_merge($session->meta ?? [], ['power_limit_kw' => $kw])]);
            $sent++;
        }

        return $sent;
    }
}
