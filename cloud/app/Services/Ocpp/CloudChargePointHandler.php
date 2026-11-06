<?php

namespace App\Services\Ocpp;

use App\Models\Charger;
use App\Models\ChargingSession;
use App\Models\Connector;
use App\Models\OcppCall;
use App\Models\OcppMessageLog;
use App\Models\OcppTransaction;
use App\Services\Charging\DirectChargingService;
use App\Services\Charging\ReservationDispatcher;
use App\Services\Monitoring\AlertService;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * پیام‌های OCPP 1.6J شارژرهایی که مستقیم به سرور ابری وصل‌اند.
 */
class CloudChargePointHandler
{
    /** ساعت دستگاه به وقت محلی است ولی با پسوند Z گزارش می‌شود. */
    private bool $localClock = false;

    public function __construct(
        private DirectChargingService $charging,
        private CloudOcppCommander $commander,
        private AlertService $alerts,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function handle(string $chargePointId, string $action, array $payload): array
    {
        $charger = Charger::where('charge_point_id', $chargePointId)->first();
        if (! $charger) {
            throw new OcppError('SecurityError', 'Unknown charge point');
        }
        $this->localClock = $charger->usesLocalClock();

        return match ($action) {
            'BootNotification' => $this->boot($charger, $payload),
            'Heartbeat' => $this->heartbeat($charger),
            'StatusNotification' => $this->status($charger, $payload),
            'Authorize' => $this->authorizeRequest($charger, $payload),
            'StartTransaction' => $this->startTransaction($charger, $payload),
            'MeterValues' => $this->meterValues($charger, $payload),
            'StopTransaction' => $this->stopTransaction($charger, $payload),
            'DataTransfer' => $this->dataTransfer($charger, $payload),
            'DiagnosticsStatusNotification', 'FirmwareStatusNotification' => $this->deviceNotification($charger, $action, $payload),
            default => throw new OcppError('NotImplemented', "Action {$action} is not supported"),
        };
    }

    /** @param array<string, mixed> $response */
    public function onCallResult(OcppCall $call, array $response): void
    {
        if ($call->action === 'RemoteStartTransaction' && ($response['status'] ?? null) === 'Rejected') {
            $this->cancelPendingRemoteStart($call, 'RemoteStartRejected');
        }
        if ($call->action === 'GetConfiguration' && empty($call->payload['key']) && is_array($response['configurationKey'] ?? null)) {
            $config = [];
            foreach ($response['configurationKey'] as $item) {
                if (is_array($item) && isset($item['key'])) {
                    $config[(string) $item['key']] = ['value' => $item['value'] ?? null, 'readonly' => (bool) ($item['readonly'] ?? false)];
                }
            }
            $charger = Charger::where('charge_point_id', $call->charge_point_id)->first();
            $charger?->update(['meta' => array_merge($charger->meta ?? [], ['ocpp_config' => $config, 'ocpp_config_at' => now()->toIso8601String()])]);
        }
    }

    public function onCallFailed(OcppCall $call): void
    {
        if ($call->action === 'RemoteStartTransaction') {
            $this->cancelPendingRemoteStart($call, 'RemoteStartFailed');
        }
    }

    public function chargerConnected(string $chargePointId, string $remoteAddress): void
    {
        Charger::where('charge_point_id', $chargePointId)->update([
            'is_online' => true,
            'connected_at' => now(),
            'last_heartbeat_at' => now(),
        ]);
        $this->alerts->resolve('charger_offline', $chargePointId);
    }

    public function chargerDisconnected(string $chargePointId): void
    {
        Charger::where('charge_point_id', $chargePointId)->update(['is_online' => false, 'connected_at' => null]);
        $charger = Charger::where('charge_point_id', $chargePointId)->first();
        if ($charger) {
            $this->alerts->raise('charger_offline', $chargePointId, 'warning', 'شارژر '.$chargePointId.' از سرور قطع شد.', $charger->station_id);
        }
    }

    public function housekeeping(): void
    {
        app(CloudLoadBalancer::class)->apply();

        $minutes = max(1, (int) $this->commander->setting('start_timeout_minutes', 3));
        ChargingSession::where('status', 'active')
            ->whereNull('ocpp_transaction_id')
            ->where('created_at', '<', now()->subMinutes($minutes))
            ->get()
            ->filter(fn (ChargingSession $s) => ($s->meta['direct_ocpp'] ?? false) && ($s->meta['edge_state'] ?? null) === 'starting')
            ->each(fn (ChargingSession $s) => $this->charging->cancel($s, 'NoCableConnected'));

        // شارژر ریست شد یا تراکنش را رها کرد و StopTransaction نفرستاد.
        $grace = max(30, (int) $this->commander->setting('lost_transaction_grace_seconds', 90));
        ChargingSession::where('status', 'active')
            ->whereNotNull('ocpp_transaction_id')
            ->get()
            ->filter(fn (ChargingSession $s) => ! empty($s->meta['lost_since']) && CarbonImmutable::parse($s->meta['lost_since'])->lt(now()->subSeconds($grace)))
            ->each(function (ChargingSession $s) {
                $this->charging->closeLost($s, (string) ($s->meta['lost_mark'] ?? 'TransactionLost'));
                app(CloudLoadBalancer::class)->apply($s->station_id);
            });

        $days = max(1, (int) $this->commander->setting('log_retention_days', 14));
        OcppMessageLog::where('created_at', '<', now()->subDays($days))->limit(5000)->delete();
        OcppCall::where('created_at', '<', now()->subDays($days))->limit(5000)->delete();
    }

    /** @return array<string, mixed> */
    private function boot(Charger $charger, array $payload): array
    {
        $this->require($payload, ['chargePointVendor', 'chargePointModel']);
        $charger->update([
            'vendor' => substr((string) $payload['chargePointVendor'], 0, 20),
            'model' => substr((string) $payload['chargePointModel'], 0, 20),
            'serial_number' => isset($payload['chargePointSerialNumber']) ? substr((string) $payload['chargePointSerialNumber'], 0, 25) : $charger->serial_number,
            'firmware_version' => isset($payload['firmwareVersion']) ? substr((string) $payload['firmwareVersion'], 0, 50) : $charger->firmware_version,
            'last_boot_at' => now(),
            'last_heartbeat_at' => now(),
            'is_online' => true,
        ]);

        ChargingSession::where('charger_id', $charger->id)->where('status', 'active')->whereNotNull('ocpp_transaction_id')
            ->get()->each(fn (ChargingSession $s) => $this->markLost($s, 'Reboot'));

        // پس از راه‌اندازی، نمونه‌برداری کنتور را برای صورتحساب دقیق تنظیم می‌کنیم.
        $this->commander->dispatch($charger->charge_point_id, 'ChangeConfiguration', ['key' => 'MeterValueSampleInterval', 'value' => '30']);
        $this->commander->dispatch($charger->charge_point_id, 'ChangeConfiguration', [
            'key' => 'MeterValuesSampledData',
            'value' => 'Energy.Active.Import.Register,Power.Active.Import,Current.Import,Voltage,SoC,Temperature',
        ]);
        $this->commander->dispatch($charger->charge_point_id, 'GetConfiguration', []);

        return [
            'status' => 'Accepted',
            'currentTime' => $this->deviceNow(),
            'interval' => max(10, (int) $this->commander->setting('heartbeat_interval', 60)),
        ];
    }

    /** @return array<string, mixed> */
    private function heartbeat(Charger $charger): array
    {
        $charger->update(['last_heartbeat_at' => now(), 'is_online' => true]);

        return ['currentTime' => $this->deviceNow()];
    }

    /** @return array<string, mixed> */
    private function status(Charger $charger, array $payload): array
    {
        $this->require($payload, ['connectorId', 'errorCode', 'status']);
        $number = (int) $payload['connectorId'];
        $status = (string) $payload['status'];
        $error = (string) $payload['errorCode'];

        if ($number === 0) {
            $charger->update(['ocpp_status' => $status, 'error_code' => $error === 'NoError' ? null : $error, 'last_heartbeat_at' => now()]);

            return [];
        }

        $connector = Connector::firstOrCreate(
            ['charger_id' => $charger->id, 'connector_number' => $number],
            ['operational_status' => 'active', 'max_power_kw' => $charger->max_power_kw, 'ocpp_status' => $status],
        );
        $connector->update([
            'ocpp_status' => $status,
            'error_code' => $error === 'NoError' ? null : $error,
            'status_updated_at' => now(),
        ]);

        $active = ChargingSession::where('connector_id', $connector->id)->where('status', 'active')->first();
        if ($active && ! $active->ocpp_transaction_id && $status === 'Finishing') {
            // شروع از راه دور پذیرفته شد ولی خودرو/شارژر بدون StartTransaction کار را تمام کرد.
            $this->charging->cancel($active, 'StartAborted');
        } elseif ($active) {
            $this->charging->markIdle($active, in_array($status, ['SuspendedEV', 'Finishing'], true));
            if ($active->ocpp_transaction_id && in_array($status, ['Available', 'Preparing'], true)) {
                $this->markLost($active, 'TransactionLost');
            } elseif (in_array($status, ['Charging', 'SuspendedEV', 'SuspendedEVSE'], true)) {
                $this->clearLost($active);
            }
        }

        $key = $charger->charge_point_id.'#'.$number;
        if ($status === 'Faulted') {
            $this->alerts->raise('connector_faulted', $key, 'critical', 'خطای کانکتور '.$key.': '.$error, $charger->station_id);
        } else {
            $this->alerts->resolve('connector_faulted', $key);
        }

        return [];
    }

    /** @return array<string, mixed> */
    private function authorizeRequest(Charger $charger, array $payload): array
    {
        $this->require($payload, ['idTag']);

        return ['idTagInfo' => ['status' => $this->charging->authorizeIdTag($charger, (string) $payload['idTag'])['status']]];
    }

    /** @return array<string, mixed> */
    private function startTransaction(Charger $charger, array $payload): array
    {
        $this->require($payload, ['connectorId', 'idTag', 'meterStart', 'timestamp']);
        $number = (int) $payload['connectorId'];
        $idTag = (string) $payload['idTag'];
        $meterStart = max(0, (int) $payload['meterStart']);
        $startedAt = $this->timestamp($payload['timestamp']);

        $result = DB::transaction(function () use ($charger, $number, $idTag, $meterStart, $startedAt) {
            $connector = Connector::where('charger_id', $charger->id)->where('connector_number', $number)->first();
            $tx = OcppTransaction::create([
                'charge_point_id' => $charger->charge_point_id,
                'connector_number' => $number,
                'id_tag' => substr($idTag, 0, 20),
                'meter_start_wh' => $meterStart,
                'started_at' => $startedAt,
            ]);

            $session = $connector ? $this->pendingSession($connector, $idTag) : null;
            $status = 'Accepted';
            if (! $session) {
                $auth = $connector ? $this->charging->authorizeIdTag($charger, $idTag) : ['status' => 'Invalid', 'user' => null];
                $status = $auth['status'];
                if ($status === 'Accepted' && $auth['user'] && $connector) {
                    if (ChargingSession::where('user_id', $auth['user']->id)->where('status', 'active')->exists()) {
                        $status = 'ConcurrentTx';
                    } else {
                        $session = $this->charging->startFromCharger($auth['user'], $connector, $idTag, $startedAt);
                    }
                }
            }

            if ($session) {
                $session->update([
                    'ocpp_transaction_id' => $tx->id,
                    'meter_start_wh' => $meterStart,
                    'started_at' => $startedAt,
                    'meta' => array_merge($session->meta ?? [], ['edge_state' => 'charging']),
                ]);
                $tx->update(['charging_session_id' => $session->id]);
                if ($session->user_id) {
                    ReservationDispatcher::checkIn($session->user_id, $session->station_id);
                }
            }
            $tx->update(['id_tag_status' => $status]);
            if ($connector && $status === 'Accepted') {
                $connector->update(['ocpp_status' => 'Charging', 'status_updated_at' => now()]);
            }

            return ['transactionId' => $tx->id, 'idTagInfo' => ['status' => $status]];
        });

        app(CloudLoadBalancer::class)->apply($charger->station_id);

        return $result;
    }

    /** @return array<string, mixed> */
    private function meterValues(Charger $charger, array $payload): array
    {
        $this->require($payload, ['connectorId', 'meterValue']);
        if (! is_array($payload['meterValue'])) {
            return [];
        }
        $this->storeLiveReading($charger, (int) $payload['connectorId'], $payload['meterValue']);

        $transactionId = $payload['transactionId'] ?? null;
        $session = $this->sessionFor($charger, ((int) $transactionId) > 0 ? $transactionId : null, (int) $payload['connectorId']);
        if (! $session) {
            return [];
        }
        if (((int) $transactionId) > 0) {
            $this->clearLost($session);
        }

        $stop = false;
        foreach ($payload['meterValue'] as $mv) {
            if (! is_array($mv) || ($reading = MeterValueParser::parse($mv)) === null) {
                continue;
            }
            $reading['sampled_at'] = isset($mv['timestamp']) ? $this->timestamp($mv['timestamp']) : now();
            $stop = $this->charging->applyMeterReading($session, $reading, $mv) || $stop;
            $session->refresh();
        }

        if ($stop && ! ($session->meta['auto_stop_sent'] ?? false) && $session->ocpp_transaction_id) {
            $session->update(['meta' => array_merge($session->meta ?? [], ['auto_stop_sent' => true])]);
            $this->commander->dispatch($charger->charge_point_id, 'RemoteStopTransaction', ['transactionId' => (int) $session->ocpp_transaction_id]);
        }

        return [];
    }

    /** آخرین مقادیر کنتور هر کانکتور، حتی بیرون از جلسهٔ شارژ، برای پنل کنترل. @param array<int, mixed> $meterValues */
    private function storeLiveReading(Charger $charger, int $number, array $meterValues): void
    {
        if ($number < 1) {
            return;
        }
        $reading = null;
        foreach ($meterValues as $mv) {
            if (is_array($mv) && ($parsed = MeterValueParser::parse($mv)) !== null) {
                $reading = array_merge($reading ?? [], $parsed);
            }
        }
        if ($reading === null) {
            return;
        }
        $connector = Connector::where('charger_id', $charger->id)->where('connector_number', $number)->first();
        if (! $connector) {
            return;
        }
        $live = array_filter([
            'power_kw' => isset($reading['power_w']) ? round(max(0, (float) $reading['power_w']) / 1000, 2) : null,
            'voltage_v' => isset($reading['voltage_v']) ? round((float) $reading['voltage_v'], 1) : null,
            'current_a' => isset($reading['current_a']) ? round((float) $reading['current_a'], 1) : null,
            'temp_c' => isset($reading['temp_c']) ? round((float) $reading['temp_c'], 1) : null,
            'soc' => $reading['soc'] ?? null,
            'energy_wh' => $reading['energy_wh'] ?? null,
        ], fn ($v) => $v !== null);
        $live['at'] = now()->toIso8601String();
        $connector->update(['meta' => array_merge($connector->meta ?? [], ['live' => $live])]);
    }

    /** @return array<string, mixed> */
    private function stopTransaction(Charger $charger, array $payload): array
    {
        $this->require($payload, ['transactionId', 'meterStop', 'timestamp']);
        $meterStop = max(0, (int) $payload['meterStop']);
        $stoppedAt = $this->timestamp($payload['timestamp']);
        $reason = (string) ($payload['reason'] ?? 'Local');

        $tx = OcppTransaction::where('id', (int) $payload['transactionId'])->where('charge_point_id', $charger->charge_point_id)->first();
        if ($tx) {
            $tx->update(['meter_stop_wh' => $meterStop, 'stopped_at' => $stoppedAt, 'stop_reason' => substr($reason, 0, 32)]);
            $session = $tx->session;
            if ($session && $session->status === 'active') {
                foreach ((array) ($payload['transactionData'] ?? []) as $mv) {
                    if (is_array($mv) && ($reading = MeterValueParser::parse($mv)) !== null) {
                        $this->charging->applyMeterReading($session, $reading, $mv);
                        $session->refresh();
                    }
                }
                $this->charging->finalize($session, $meterStop, $stoppedAt, $reason);
                app(CloudLoadBalancer::class)->apply($charger->station_id);
            }
        } else {
            Log::warning('ocpp.cloud.stop_unknown_transaction', ['charge_point' => $charger->charge_point_id]);
        }

        return isset($payload['idTag']) ? ['idTagInfo' => ['status' => 'Accepted']] : [];
    }

    /** @return array<string, mixed> */
    private function deviceNotification(Charger $charger, string $action, array $payload): array
    {
        $key = $action === 'FirmwareStatusNotification' ? 'firmware_status' : 'diagnostics_status';
        $charger->update(['meta' => array_merge($charger->meta ?? [], [
            $key => (string) ($payload['status'] ?? ''),
            $key.'_at' => now()->toIso8601String(),
        ])]);

        return [];
    }

    /** @return array<string, mixed> */
    private function dataTransfer(Charger $charger, array $payload): array
    {
        $vendor = strtolower((string) ($payload['vendorId'] ?? ''));
        if ($vendor === '' || $vendor !== strtolower((string) $charger->vendor)) {
            return ['status' => 'UnknownVendorId'];
        }

        $data = is_array($payload['data'] ?? null) ? $payload['data'] : ['raw' => $payload['data'] ?? null];
        $charger->update(['meta' => array_merge($charger->meta ?? [], [
            'data_transfer' => [
                'message_id' => (string) ($payload['messageId'] ?? ''),
                'data' => $data,
                'at' => now()->toIso8601String(),
            ],
        ])]);

        return ['status' => 'Accepted'];
    }

    private function pendingSession(Connector $connector, string $idTag): ?ChargingSession
    {
        return ChargingSession::where('connector_id', $connector->id)
            ->where('status', 'active')
            ->whereNull('ocpp_transaction_id')
            ->whereRaw('UPPER(id_tag) = ?', [strtoupper($idTag)])
            ->latest('id')
            ->first();
    }

    private function sessionFor(Charger $charger, mixed $transactionId, int $number): ?ChargingSession
    {
        if ($transactionId !== null) {
            $session = OcppTransaction::where('id', (int) $transactionId)->where('charge_point_id', $charger->charge_point_id)->first()?->session;

            return $session?->status === 'active' ? $session : null;
        }

        $connector = Connector::where('charger_id', $charger->id)->where('connector_number', $number)->first();

        return $connector
            ? ChargingSession::where('connector_id', $connector->id)->where('status', 'active')->whereNotNull('ocpp_transaction_id')->latest('id')->first()
            : null;
    }

    private function deviceNow(): string
    {
        $now = $this->localClock ? now(Charger::DEVICE_TIMEZONE) : now()->utc();

        return $now->format('Y-m-d\TH:i:s.v\Z');
    }

    private function markLost(ChargingSession $session, string $reason): void
    {
        $meta = $session->meta ?? [];
        if (! empty($meta['lost_since']) && ($meta['lost_mark'] ?? null) === 'Reboot') {
            return;
        }
        $meta['lost_since'] ??= now()->toIso8601String();
        $meta['lost_mark'] = $reason;
        $session->update(['meta' => $meta]);
    }

    private function clearLost(ChargingSession $session): void
    {
        $meta = $session->meta ?? [];
        if (! isset($meta['lost_since'])) {
            return;
        }
        unset($meta['lost_since'], $meta['lost_mark']);
        $session->update(['meta' => $meta]);
    }

    private function cancelPendingRemoteStart(OcppCall $call, string $reason): void
    {
        $charger = Charger::where('charge_point_id', $call->charge_point_id)->first();
        $connector = $charger
            ? Connector::where('charger_id', $charger->id)->where('connector_number', (int) ($call->payload['connectorId'] ?? 0))->first()
            : null;
        $session = $connector ? $this->pendingSession($connector, (string) ($call->payload['idTag'] ?? '')) : null;
        if ($session) {
            $this->charging->cancel($session, $reason);
        }
    }

    /** @param list<string> $fields */
    private function require(array $payload, array $fields): void
    {
        foreach ($fields as $field) {
            if (! array_key_exists($field, $payload)) {
                throw new OcppError('OccurenceConstraintViolation', "Missing required field {$field}");
            }
        }
    }

    private function timestamp(mixed $value): CarbonInterface
    {
        try {
            $time = CarbonImmutable::parse((string) $value);
            if ($this->localClock) {
                $time = CarbonImmutable::parse($time->utc()->format('Y-m-d H:i:s.u'), Charger::DEVICE_TIMEZONE);
            }
            $time = $time->setTimezone(config('app.timezone'));
        } catch (\Throwable) {
            return now();
        }

        return abs($time->diffInHours(now(), true)) > 24 ? now() : $time;
    }
}
