<?php

namespace App\Services\Ocpp;

use App\Models\CachedRfidCard;
use App\Models\CachedUser;
use App\Models\CachedWallet;
use App\Models\EdgeCharger;
use App\Models\EdgeChargingSession;
use App\Models\EdgeConnector;
use App\Models\OcppCall;
use App\Models\OcppMessageLog;
use App\Models\OcppTransaction;
use App\Services\Charging\EdgeChargingService;
use App\Services\Sync\SyncOutboxService;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * منطق کسب‌وکار پیام‌های OCPP 1.6J دریافتی از شارژر (Charge Point → Central System).
 * مستقل از لایهٔ WebSocket است تا به‌سادگی تست شود.
 */
class ChargePointMessageHandler
{
    public function __construct(
        private EdgeChargingService $charging,
        private ChargePointCommander $commander,
        private SyncOutboxService $outbox,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function handle(string $chargePointId, string $action, array $payload): array
    {
        $charger = EdgeCharger::where('charge_point_id', $chargePointId)->first();
        if (! $charger) {
            throw new OcppError('SecurityError', 'Unknown charge point');
        }

        return match ($action) {
            'BootNotification' => $this->bootNotification($charger, $payload),
            'Heartbeat' => $this->heartbeat($charger),
            'StatusNotification' => $this->statusNotification($charger, $payload),
            'Authorize' => $this->authorizeRequest($charger, $payload),
            'StartTransaction' => $this->startTransaction($charger, $payload),
            'MeterValues' => $this->meterValues($charger, $payload),
            'StopTransaction' => $this->stopTransaction($charger, $payload),
            'DataTransfer' => ['status' => 'UnknownVendorId'],
            'DiagnosticsStatusNotification', 'FirmwareStatusNotification' => $this->deviceNotification($charger, $action, $payload),
            default => throw new OcppError('NotImplemented', "Action {$action} is not supported"),
        };
    }

    /**
     * پاسخ شارژر به دستوری که Edge فرستاده بود.
     *
     * @param  array<string, mixed>  $response
     */
    public function onCallResult(OcppCall $call, array $response): void
    {
        if ($call->action === 'RemoteStartTransaction' && ($response['status'] ?? null) === 'Rejected') {
            $this->cancelPendingRemoteStart($call, 'RemoteStartRejected');
        }

        if ($call->action === 'ChangeAvailability' && in_array($response['status'] ?? null, ['Accepted', 'Scheduled'], true)) {
            Log::info('ocpp.change_availability', ['charge_point' => $call->charge_point_id, 'status' => $response['status']]);
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
        EdgeCharger::where('charge_point_id', $chargePointId)->update([
            'is_online' => true,
            'status' => 'active',
            'connected_at' => now(),
            'last_heartbeat_at' => now(),
            'remote_address' => substr($remoteAddress, 0, 64),
        ]);
        $this->emitChargerStatus($chargePointId);
    }

    public function chargerDisconnected(string $chargePointId): void
    {
        EdgeCharger::where('charge_point_id', $chargePointId)->update([
            'is_online' => false,
            'status' => 'inactive',
        ]);
        $this->emitChargerStatus($chargePointId);
    }

    /** نگهداری دوره‌ای: لغو شروع‌های بی‌پاسخ و پاک‌سازی لاگ‌ها. */
    public function housekeeping(): void
    {
        $minutes = max(1, (int) $this->commander->setting('start_timeout_minutes', 3));

        EdgeChargingSession::query()
            ->where('status', 'active')
            ->whereNull('ocpp_transaction_id')
            ->where('created_at', '<', now()->subMinutes($minutes))
            ->get()
            ->each(function (EdgeChargingSession $session) {
                if (($session->meta['ocpp_state'] ?? null) === 'starting') {
                    $this->charging->cancel($session, 'NoCableConnected');
                }
            });

        $days = max(1, (int) $this->commander->setting('log_retention_days', 14));
        OcppMessageLog::where('created_at', '<', now()->subDays($days))->limit(5000)->delete();
        OcppCall::where('created_at', '<', now()->subDays($days))->limit(5000)->delete();
    }

    /** @return array<string, mixed> */
    private function bootNotification(EdgeCharger $charger, array $payload): array
    {
        $this->require($payload, ['chargePointVendor', 'chargePointModel']);

        $charger->update([
            'vendor' => $this->str($payload['chargePointVendor'], 20),
            'model' => $this->str($payload['chargePointModel'], 20),
            'serial_number' => $this->str($payload['chargePointSerialNumber'] ?? $payload['chargeBoxSerialNumber'] ?? $charger->serial_number, 25),
            'firmware_version' => $this->str($payload['firmwareVersion'] ?? $charger->firmware_version, 50),
            'last_boot_at' => now(),
            'last_heartbeat_at' => now(),
            'is_online' => true,
            'status' => 'active',
            'meta' => array_merge($charger->meta ?? [], array_filter([
                'iccid' => $payload['iccid'] ?? null,
                'imsi' => $payload['imsi'] ?? null,
                'meter_type' => $payload['meterType'] ?? null,
                'meter_serial_number' => $payload['meterSerialNumber'] ?? null,
            ], fn ($v) => $v !== null)),
        ]);

        return [
            'status' => 'Accepted',
            'currentTime' => $this->now(),
            'interval' => max(10, (int) $this->commander->setting('heartbeat_interval', 60)),
        ];
    }

    /** @return array<string, mixed> */
    private function heartbeat(EdgeCharger $charger): array
    {
        $charger->update(['last_heartbeat_at' => now(), 'is_online' => true]);

        return ['currentTime' => $this->now()];
    }

    /** @return array<string, mixed> */
    private function statusNotification(EdgeCharger $charger, array $payload): array
    {
        $this->require($payload, ['connectorId', 'errorCode', 'status']);
        $connectorId = (int) $payload['connectorId'];
        $status = (string) $payload['status'];
        $errorCode = (string) $payload['errorCode'];

        if ($connectorId === 0) {
            $charger->update([
                'ocpp_status' => $status,
                'error_code' => $errorCode === 'NoError' ? null : $errorCode,
                'last_heartbeat_at' => now(),
            ]);
            $this->emitChargerStatus($charger->charge_point_id);

            return [];
        }

        $connector = EdgeConnector::firstOrNew(['charger_id' => $charger->id, 'connector_number' => $connectorId]);
        if (! $connector->exists) {
            $connector->uuid = (string) Str::uuid();
            $connector->operational_status = 'active';
            $connector->max_power_kw = $charger->max_power_kw;
        }

        $connector->fill([
            'ocpp_status' => $status,
            'error_code' => $errorCode === 'NoError' ? null : $errorCode,
            'vendor_error_code' => isset($payload['vendorErrorCode']) ? $this->str($payload['vendorErrorCode'], 64) : null,
            'status_updated_at' => now(),
            'meta' => array_merge($connector->meta ?? [], array_filter([
                'info' => $payload['info'] ?? null,
            ], fn ($v) => $v !== null)),
        ]);
        $connector->save();

        $charger->update(['last_heartbeat_at' => now()]);
        $this->emitConnectorStatus($connector);

        return [];
    }

    /** @return array<string, mixed> */
    private function authorizeRequest(EdgeCharger $charger, array $payload): array
    {
        $this->require($payload, ['idTag']);

        return ['idTagInfo' => ['status' => $this->authorizeIdTag($charger, (string) $payload['idTag'])['status']]];
    }

    /** @return array<string, mixed> */
    private function startTransaction(EdgeCharger $charger, array $payload): array
    {
        $this->require($payload, ['connectorId', 'idTag', 'meterStart', 'timestamp']);
        $connectorNumber = (int) $payload['connectorId'];
        $idTag = (string) $payload['idTag'];
        $meterStart = max(0, (int) $payload['meterStart']);
        $startedAt = $this->timestamp($payload['timestamp']);

        return DB::transaction(function () use ($charger, $connectorNumber, $idTag, $meterStart, $startedAt) {
            $connector = EdgeConnector::where('charger_id', $charger->id)
                ->where('connector_number', $connectorNumber)
                ->first();

            $transaction = OcppTransaction::create([
                'charge_point_id' => $charger->charge_point_id,
                'connector_number' => $connectorNumber,
                'id_tag' => substr($idTag, 0, 20),
                'meter_start_wh' => $meterStart,
                'started_at' => $startedAt,
            ]);

            $session = $connector ? $this->pendingSessionFor($connector, $idTag) : null;
            $status = 'Accepted';

            if (! $session) {
                $auth = $connector
                    ? $this->authorizeIdTag($charger, $idTag)
                    : ['status' => 'Invalid', 'user' => null];
                $status = $auth['status'];

                if ($status === 'Accepted' && $auth['user'] && $connector) {
                    if (EdgeChargingSession::where('cached_user_uuid', $auth['user']->uuid)->where('status', 'active')->exists()) {
                        $status = 'ConcurrentTx';
                    } else {
                        $session = $this->charging->startFromCharger($auth['user'], $connector, $idTag, $startedAt);
                    }
                }
            }

            if ($session) {
                $session->update([
                    'ocpp_transaction_id' => $transaction->id,
                    'meter_start_wh' => $meterStart,
                    'started_at' => $startedAt,
                    'meta' => array_merge($session->meta ?? [], ['ocpp_state' => 'charging']),
                ]);
                $transaction->update(['charging_session_id' => $session->id]);
                $this->charging->emitStarted($session->fresh());
            }

            $transaction->update(['id_tag_status' => $status]);

            if ($connector && $status === 'Accepted') {
                $connector->update(['ocpp_status' => 'Charging', 'status_updated_at' => now()]);
            }

            return [
                'transactionId' => $transaction->id,
                'idTagInfo' => ['status' => $status],
            ];
        });
    }

    /** @return array<string, mixed> */
    private function meterValues(EdgeCharger $charger, array $payload): array
    {
        $this->require($payload, ['connectorId', 'meterValue']);

        $session = $this->sessionForTransaction($charger, $payload['transactionId'] ?? null, (int) $payload['connectorId']);
        if (! $session || ! is_array($payload['meterValue'])) {
            return [];
        }

        $stopNeeded = false;
        foreach ($payload['meterValue'] as $meterValue) {
            if (! is_array($meterValue)) {
                continue;
            }
            $reading = MeterValueParser::parse($meterValue);
            if ($reading === null) {
                continue;
            }
            $reading['sampled_at'] = isset($meterValue['timestamp']) ? $this->timestamp($meterValue['timestamp']) : now();
            $stopNeeded = $this->charging->applyMeterReading($session, $reading, $meterValue) || $stopNeeded;
            $session->refresh();
        }

        if ($stopNeeded && ! ($session->meta['low_balance_stop_sent'] ?? false) && $session->ocpp_transaction_id) {
            $session->update(['meta' => array_merge($session->meta ?? [], ['low_balance_stop_sent' => true])]);
            $this->commander->dispatch($charger->charge_point_id, 'RemoteStopTransaction', [
                'transactionId' => (int) $session->ocpp_transaction_id,
            ]);
            Log::info('ocpp.low_balance_stop', ['session' => $session->uuid]);
        }

        return [];
    }

    /** @return array<string, mixed> */
    private function stopTransaction(EdgeCharger $charger, array $payload): array
    {
        $this->require($payload, ['transactionId', 'meterStop', 'timestamp']);
        $meterStop = max(0, (int) $payload['meterStop']);
        $stoppedAt = $this->timestamp($payload['timestamp']);
        $reason = (string) ($payload['reason'] ?? 'Local');

        $transaction = OcppTransaction::where('id', (int) $payload['transactionId'])
            ->where('charge_point_id', $charger->charge_point_id)
            ->first();

        if ($transaction) {
            $transaction->update([
                'meter_stop_wh' => $meterStop,
                'stopped_at' => $stoppedAt,
                'stop_reason' => substr($reason, 0, 32),
            ]);

            $session = $transaction->session;
            if ($session) {
                foreach ((array) ($payload['transactionData'] ?? []) as $meterValue) {
                    if (is_array($meterValue) && ($reading = MeterValueParser::parse($meterValue)) !== null) {
                        $reading['sampled_at'] = isset($meterValue['timestamp']) ? $this->timestamp($meterValue['timestamp']) : $stoppedAt;
                        $this->charging->applyMeterReading($session, $reading, $meterValue);
                        $session->refresh();
                    }
                }

                if ($session->status === 'active') {
                    $this->charging->finalize($session, $meterStop, $stoppedAt, $reason);
                } else {
                    Log::info('ocpp.stop_after_local_close', [
                        'session' => $session->uuid,
                        'meter_stop_wh' => $meterStop,
                        'billed_kwh' => (float) $session->energy_kwh,
                    ]);
                }
            }
        } else {
            Log::warning('ocpp.stop_unknown_transaction', ['charge_point' => $charger->charge_point_id, 'payload' => $payload]);
        }

        return isset($payload['idTag']) ? ['idTagInfo' => ['status' => 'Accepted']] : [];
    }

    /** @return array<string, mixed> */
    private function deviceNotification(EdgeCharger $charger, string $action, array $payload): array
    {
        $key = $action === 'FirmwareStatusNotification' ? 'firmware_status' : 'diagnostics_status';
        $charger->update(['meta' => array_merge($charger->meta ?? [], [
            $key => (string) ($payload['status'] ?? ''),
            $key.'_at' => now()->toIso8601String(),
        ])]);

        return [];
    }

    /**
     * @return array{status:string,user:?CachedUser}
     */
    public function authorizeIdTag(EdgeCharger $charger, string $idTag): array
    {
        $pending = EdgeChargingSession::query()
            ->where('charger_id', $charger->id)
            ->where('status', 'active')
            ->whereRaw('UPPER(id_tag) = ?', [strtoupper($idTag)])
            ->first();
        if ($pending) {
            return ['status' => 'Accepted', 'user' => CachedUser::where('uuid', $pending->cached_user_uuid)->first()];
        }

        $card = CachedRfidCard::query()->whereRaw('UPPER(id_tag) = ?', [strtoupper($idTag)])->first();
        if (! $card) {
            return ['status' => 'Invalid', 'user' => null];
        }

        if ($card->status !== 'active') {
            return ['status' => 'Blocked', 'user' => null];
        }

        if ($card->expires_at && $card->expires_at->isPast()) {
            return ['status' => 'Expired', 'user' => null];
        }

        $user = CachedUser::where('uuid', $card->cached_user_uuid)->first();
        if (! $user || ($user->status ?? 'active') !== 'active') {
            return ['status' => 'Blocked', 'user' => null];
        }

        $wallet = CachedWallet::where('cached_user_id', $user->id)->first();
        if (! $wallet || $this->charging->spendable($wallet) < $this->charging->minimumBalance()) {
            return ['status' => 'Blocked', 'user' => $user];
        }

        return ['status' => 'Accepted', 'user' => $user];
    }

    private function pendingSessionFor(EdgeConnector $connector, string $idTag): ?EdgeChargingSession
    {
        return EdgeChargingSession::query()
            ->where('connector_id', $connector->id)
            ->where('status', 'active')
            ->whereNull('ocpp_transaction_id')
            ->whereRaw('UPPER(id_tag) = ?', [strtoupper($idTag)])
            ->latest('id')
            ->first();
    }

    private function sessionForTransaction(EdgeCharger $charger, mixed $transactionId, int $connectorNumber): ?EdgeChargingSession
    {
        if ($transactionId !== null) {
            $session = OcppTransaction::where('id', (int) $transactionId)
                ->where('charge_point_id', $charger->charge_point_id)
                ->first()?->session;

            return $session?->status === 'active' ? $session : null;
        }

        $connector = EdgeConnector::where('charger_id', $charger->id)->where('connector_number', $connectorNumber)->first();

        return $connector
            ? EdgeChargingSession::where('connector_id', $connector->id)
                ->where('status', 'active')
                ->whereNotNull('ocpp_transaction_id')
                ->latest('id')
                ->first()
            : null;
    }

    private function cancelPendingRemoteStart(OcppCall $call, string $reason): void
    {
        $idTag = (string) ($call->payload['idTag'] ?? '');
        $connectorNumber = (int) ($call->payload['connectorId'] ?? 0);
        $charger = EdgeCharger::where('charge_point_id', $call->charge_point_id)->first();
        if (! $charger || $idTag === '') {
            return;
        }

        $connector = EdgeConnector::where('charger_id', $charger->id)->where('connector_number', $connectorNumber)->first();
        $session = $connector ? $this->pendingSessionFor($connector, $idTag) : null;
        if ($session) {
            $this->charging->cancel($session, $reason);
        }
    }

    private function emitConnectorStatus(EdgeConnector $connector): void
    {
        $connector->loadMissing('charger');
        $this->outbox->enqueue('connector.status', [
            'cloud_connector_uuid' => $connector->cloud_connector_uuid,
            'cloud_charger_uuid' => $connector->charger?->cloud_charger_uuid,
            'charge_point_id' => $connector->charger?->charge_point_id,
            'connector_number' => (int) $connector->connector_number,
            'ocpp_status' => $connector->ocpp_status,
            'error_code' => $connector->error_code,
            'charger_online' => (bool) $connector->charger?->is_online,
            'at' => now()->toIso8601String(),
        ], 'connector', $connector->uuid);
    }

    private function emitChargerStatus(string $chargePointId): void
    {
        $charger = EdgeCharger::where('charge_point_id', $chargePointId)->first();
        if (! $charger) {
            return;
        }

        $this->outbox->enqueue('charger.status', [
            'cloud_charger_uuid' => $charger->cloud_charger_uuid,
            'charge_point_id' => $charger->charge_point_id,
            'is_online' => (bool) $charger->is_online,
            'ocpp_status' => $charger->ocpp_status,
            'error_code' => $charger->error_code,
            'vendor' => $charger->vendor,
            'model' => $charger->model,
            'firmware_version' => $charger->firmware_version,
            'at' => now()->toIso8601String(),
        ], 'charger', $charger->uuid);
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

    private function str(mixed $value, int $max): ?string
    {
        return $value === null ? null : substr((string) $value, 0, $max);
    }

    private function timestamp(mixed $value): CarbonInterface
    {
        try {
            $time = CarbonImmutable::parse((string) $value)->utc();
        } catch (\Throwable) {
            return now();
        }

        // ساعت شارژرهای بدون RTC/NTP ممکن است نادرست باشد.
        return abs($time->diffInHours(now(), true)) > 24 ? now() : $time;
    }

    private function now(): string
    {
        return now()->utc()->format('Y-m-d\TH:i:s.v\Z');
    }
}
