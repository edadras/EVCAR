<?php

namespace App\Services\Ocpp;

use App\Models\Charger;

/**
 * ارسال دستور به شارژرهایی که مستقیم به سرور OCPP ابری وصل هستند.
 */
class CloudOcppCommander
{
    /** داخل حلقهٔ ocpp:serve منتظر ماندن برای پاسخ باعث قفل شدن حلقه می‌شود. */
    private static bool $insideServer = false;

    public function __construct(private OcppCallQueue $queue) {}

    public static function markInsideServer(bool $inside = true): void
    {
        self::$insideServer = $inside;
    }

    public static function insideServer(): bool
    {
        return self::$insideServer;
    }

    public function setting(string $key, mixed $default = null): mixed
    {
        return config('safircharge.ocpp.'.$key, $default);
    }

    public function isConnected(Charger $charger): bool
    {
        return (bool) $charger->is_online && $charger->connected_at !== null;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function call(string $chargePointId, string $action, array $payload = [], ?float $timeout = null): array
    {
        if (self::$insideServer) {
            $this->queue->enqueue($chargePointId, $action, $payload);

            return ['status' => 'Pending', 'queued' => true];
        }

        $charger = Charger::where('charge_point_id', $chargePointId)->first();
        if (! $charger) {
            throw new \RuntimeException("شارژر {$chargePointId} تعریف نشده است.");
        }
        if (! $this->isConnected($charger)) {
            throw new \RuntimeException("شارژر {$chargePointId} به سرور OCPP متصل نیست.");
        }

        return $this->queue->call($chargePointId, $action, $payload, $timeout ?? (float) $this->setting('call_timeout', 30));
    }

    /** @param array<string, mixed> $payload */
    public function dispatch(string $chargePointId, string $action, array $payload = []): void
    {
        $this->queue->enqueue($chargePointId, $action, $payload);
    }
}
