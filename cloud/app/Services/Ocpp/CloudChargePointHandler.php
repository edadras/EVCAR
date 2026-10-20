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

        return match ($action) {
            'BootNotification' => $this->boot($charger, $payload),
            'Heartbeat' => $this->heartbeat($charger),
            'StatusNotification' => $this->status($charger, $payload),
            'Authorize' => $this->authorizeRequest($charger, $payload),
            'StartTransaction' => $this->startTransaction($charger, $payload),
            'MeterValues' => $this->meterValues($charger, $payload),
            'StopTransaction' => $this->stopTransaction($charger, $payload),
            'DataTransfer' => ['status' => 'UnknownVendorId'],
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

        // پس از راه‌اندازی، نمونه‌برداری کنتور را برای صورتحساب دقیق تنظیم می‌کنیم.
        $this->commander->dispatch($charger->charge_point_id, 'ChangeConfiguration', ['key' => 'MeterValueSampleInterval', 'value' => '30']);
        $this->commander->dispatch($charger->charge_point_id, 'ChangeConfiguration', [
            'key' => 'MeterValuesSampledData',
            'value' => 'Energy.Active.Import.Register,Power.Active.Import,Current.Import,Voltage,SoC',
        ]);

        return [
            'status' => 'Accepted',
            'currentTime' => now()->utc()->format('Y-m-d\TH:i:s.v\Z'),
            'interval' => max(10, (int) $this->commander->setting('heartbeat_interval', 60)),
        ];
    }

    /** @return array<string, mixed> */
    private function heartbeat(Charger $charger): array
    {
        $charger->update(['last_heartbeat_at' => now(), 'is_online' => true]);

        return ['currentTime' => now()->utc()->format('Y-m-d\TH:i:s.v\Z')];
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
        if ($active) {
            $this->charging->markIdle($active, in_array($status, ['SuspendedEV', 'Finishing'], true));
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
        $session = $this->sessionFor($charger, $payload['transactionId'] ?? null, (int) $payload['connectorId']);
        if (! $session || ! is_array($payload['meterValue'])) {
            return [];
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
            $time = CarbonImmutable::parse((string) $value)->utc();
        } catch (\Throwable) {
            return now();
        }

        return abs($time->diffInHours(now(), true)) > 24 ? now() : $time;
    }
}
