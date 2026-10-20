<?php

namespace App\Services\Ocpp;

use App\Models\EdgeCharger;
use App\Services\Station\StationConfigResolver;

/**
 * نقطهٔ واحد ارسال دستور به شارژر، مستقل از درایور (builtin / gateway / simulator).
 */
class ChargePointCommander
{
    public const DRIVER_BUILTIN = 'builtin';

    public const DRIVER_GATEWAY = 'gateway';

    public const DRIVER_SIMULATOR = 'simulator';

    /** Set while running inside the ocpp:serve event loop, where blocking waits would deadlock. */
    private static bool $insideServer = false;

    public function __construct(
        private StationConfigResolver $config,
        private OcppCallQueue $queue,
        private OcppGatewayClient $gateway,
    ) {}

    public static function markInsideServer(bool $inside = true): void
    {
        self::$insideServer = $inside;
    }

    public static function insideServer(): bool
    {
        return self::$insideServer;
    }

    public function driver(): string
    {
        $driver = (string) ($this->config->ocpp()['driver'] ?? self::DRIVER_BUILTIN);

        return in_array($driver, [self::DRIVER_BUILTIN, self::DRIVER_GATEWAY, self::DRIVER_SIMULATOR], true)
            ? $driver
            : self::DRIVER_BUILTIN;
    }

    /** True when energy and transaction state come from real OCPP messages. */
    public function isMetered(): bool
    {
        return $this->driver() === self::DRIVER_BUILTIN;
    }

    public function isConnected(EdgeCharger $charger): bool
    {
        return match ($this->driver()) {
            self::DRIVER_BUILTIN => (bool) $charger->is_online,
            default => true,
        };
    }

    public function setting(string $key, mixed $default = null): mixed
    {
        return $this->config->ocpp()[$key] ?? $default;
    }

    /**
     * دستور را می‌فرستد و تا رسیدن پاسخ شارژر صبر می‌کند.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function call(string $chargePointId, string $action, array $payload = [], ?float $timeout = null): array
    {
        return match ($this->driver()) {
            self::DRIVER_SIMULATOR => ['status' => 'Accepted', 'simulated' => true],
            self::DRIVER_GATEWAY => $this->gatewayCall($chargePointId, $action, $payload),
            default => self::$insideServer
                ? $this->dispatchOnly($chargePointId, $action, $payload)
                : $this->ensureOnline($chargePointId)->queue->call(
                    $chargePointId,
                    $action,
                    $payload,
                    $timeout ?? (float) $this->setting('call_timeout', 30),
                ),
        };
    }

    /**
     * دستور را بدون انتظار برای پاسخ در صف می‌گذارد.
     *
     * @param  array<string, mixed>  $payload
     */
    public function dispatch(string $chargePointId, string $action, array $payload = []): void
    {
        match ($this->driver()) {
            self::DRIVER_SIMULATOR => null,
            self::DRIVER_GATEWAY => $this->gatewayCall($chargePointId, $action, $payload),
            default => $this->queue->enqueue($chargePointId, $action, $payload),
        };
    }

    private function ensureOnline(string $chargePointId): self
    {
        $online = EdgeCharger::where('charge_point_id', $chargePointId)->value('is_online');
        if ($online === null) {
            throw new \RuntimeException("شارژر {$chargePointId} در این ایستگاه تعریف نشده است.");
        }
        if (! $online) {
            throw new \RuntimeException("شارژر {$chargePointId} به کنترلر ایستگاه متصل نیست (OCPP آفلاین).");
        }

        return $this;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function dispatchOnly(string $chargePointId, string $action, array $payload): array
    {
        $this->queue->enqueue($chargePointId, $action, $payload);

        return ['status' => 'Pending', 'queued' => true];
    }

    /** @return array<string, mixed> */
    public function gatewayRemoteStopCharger(int $gatewayChargerId): array
    {
        return $this->gateway->remoteStopCharger($gatewayChargerId);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function gatewayCall(string $chargePointId, string $action, array $payload): array
    {
        if (! $this->gateway->configured()) {
            throw new \RuntimeException('OCPP Gateway پیکربندی نشده (OCPP_API_URL).');
        }

        $response = $this->gateway->send($chargePointId, $action, $payload);
        $result = is_array($response['result'] ?? null) ? $response['result'] : $response;

        return array_merge(['status' => 'Accepted'], $result);
    }
}
