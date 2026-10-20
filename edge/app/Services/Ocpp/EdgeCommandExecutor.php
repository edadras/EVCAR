<?php

namespace App\Services\Ocpp;

use App\Models\CachedUser;
use App\Models\EdgeCharger;
use App\Models\EdgeChargingSession;
use App\Models\EdgeConnector;
use App\Services\Charging\EdgeChargingService;

/**
 * اجرای دستورهای مدیریتی (از پنل محلی یا ابر) روی شارژر.
 */
class EdgeCommandExecutor
{
    /** پاسخ‌هایی از شارژر که به معنای عدم اجرای دستور است. */
    private const FAILED_STATUSES = ['Rejected', 'NotSupported', 'UnlockFailed', 'NotImplemented'];

    public function __construct(
        private ChargePointCommander $commander,
        private EdgeChargingService $charging,
    ) {}

    /** @param array<string, mixed> $command */
    public function execute(array $command): array
    {
        $type = (string) ($command['command_type'] ?? '');
        $chargePointId = (string) ($command['charge_point_id'] ?? '');
        $connectorNumber = (int) ($command['connector_number'] ?? 1);
        $payload = is_array($command['payload'] ?? null) ? $command['payload'] : [];

        if ($chargePointId === '') {
            throw new \InvalidArgumentException('charge_point_id برای دستور الزامی است.');
        }

        if ($type === 'remote_start') {
            return $this->remoteStart($chargePointId, $connectorNumber, $payload);
        }

        if ($type === 'remote_stop') {
            return $this->remoteStop($chargePointId, $connectorNumber, $payload);
        }

        [$action, $ocppPayload] = match ($type) {
            'reset' => ['Reset', ['type' => in_array($payload['type'] ?? 'Soft', ['Soft', 'Hard'], true) ? ($payload['type'] ?? 'Soft') : 'Soft']],
            'unlock' => ['UnlockConnector', ['connectorId' => $connectorNumber]],
            'change_availability' => ['ChangeAvailability', [
                'connectorId' => $connectorNumber,
                'type' => ($payload['type'] ?? 'Inoperative') === 'Operative' ? 'Operative' : 'Inoperative',
            ]],
            'set_charging_profile' => ['SetChargingProfile', $this->chargingProfile($connectorNumber, $payload)],
            'get_diagnostics' => ['GetDiagnostics', ['location' => (string) ($payload['location'] ?? 'ftp://diagnostics/')]],
            'update_firmware' => ['UpdateFirmware', [
                'location' => (string) ($payload['location'] ?? ''),
                'retrieveDate' => (string) ($payload['retrieveDate'] ?? now()->utc()->format('Y-m-d\TH:i:s\Z')),
            ]],
            'trigger_status' => ['TriggerMessage', ['requestedMessage' => 'StatusNotification', 'connectorId' => $connectorNumber]],
            'get_configuration' => ['GetConfiguration', isset($payload['key']) ? ['key' => array_values((array) $payload['key'])] : []],
            'change_configuration' => ['ChangeConfiguration', [
                'key' => (string) ($payload['key'] ?? ''),
                'value' => (string) ($payload['value'] ?? ''),
            ]],
            default => throw new \InvalidArgumentException('نوع دستور ناشناخته: '.$type),
        };

        if ($action === 'UpdateFirmware' && $ocppPayload['location'] === '') {
            throw new \InvalidArgumentException('آدرس فایل Firmware (location) الزامی است.');
        }

        return $this->send($chargePointId, $action, $ocppPayload);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function send(string $chargePointId, string $action, array $payload): array
    {
        $response = $this->commander->call($chargePointId, $action, $payload);
        $status = (string) ($response['status'] ?? '');

        if (in_array($status, self::FAILED_STATUSES, true)) {
            throw new \RuntimeException("شارژر دستور {$action} را اجرا نکرد (پاسخ: {$status}).");
        }

        return array_merge([
            'action' => $action,
            'charge_point_id' => $chargePointId,
            'simulated' => $this->commander->driver() === ChargePointCommander::DRIVER_SIMULATOR,
        ], $response);
    }

    /**
     * شروع شارژ برای کاربر از راه دور (درخواست اپ آنلاین از طریق ابر).
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function remoteStart(string $chargePointId, int $connectorNumber, array $payload): array
    {
        $connector = $this->connector($chargePointId, $connectorNumber);
        $user = CachedUser::where('cloud_user_uuid', (string) ($payload['cloud_user_uuid'] ?? ''))->first();
        if (! $user) {
            throw new \RuntimeException('کاربر هنوز با این ایستگاه همگام نشده است.');
        }

        $session = $this->charging->startOnConnector(
            $user,
            $connector,
            (string) ($payload['auth_method'] ?? 'app'),
            array_filter([
                'target_soc' => isset($payload['target_soc']) ? (int) $payload['target_soc'] : null,
                'requested_from' => 'cloud',
            ], fn ($v) => $v !== null),
        );

        if (! empty($payload['cloud_session_uuid'])) {
            $session->update(['cloud_session_uuid' => (string) $payload['cloud_session_uuid']]);
        }

        return [
            'session_uuid' => $session->uuid,
            'status' => $session->status,
            'ocpp_state' => $session->meta['ocpp_state'] ?? null,
            'simulated' => $this->commander->driver() === ChargePointCommander::DRIVER_SIMULATOR,
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function remoteStop(string $chargePointId, int $connectorNumber, array $payload): array
    {
        $session = null;
        if (! empty($payload['session_uuid']) || ! empty($payload['cloud_session_uuid'])) {
            $session = EdgeChargingSession::where('status', 'active')
                ->where(function ($q) use ($payload) {
                    $q->where('uuid', (string) ($payload['session_uuid'] ?? ''))
                        ->orWhere('cloud_session_uuid', (string) ($payload['cloud_session_uuid'] ?? ''));
                })
                ->first();
        }

        if (! $session && ! empty($payload['transaction_id'])) {
            $session = EdgeChargingSession::where('status', 'active')
                ->where('ocpp_transaction_id', (int) $payload['transaction_id'])
                ->first();
        }

        if (! $session) {
            $connector = $this->connector($chargePointId, $connectorNumber);
            $session = EdgeChargingSession::where('connector_id', $connector->id)
                ->where('status', 'active')
                ->latest('started_at')
                ->first();
        }

        if ($session) {
            $stopped = $this->charging->stopSession($session, 'Remote');

            return [
                'session_uuid' => $stopped->uuid,
                'status' => $stopped->status,
                'energy_kwh' => (float) $stopped->energy_kwh,
                'cost_amount' => (float) $stopped->cost_amount,
                'simulated' => $this->commander->driver() === ChargePointCommander::DRIVER_SIMULATOR,
            ];
        }

        if (! empty($payload['transaction_id'])) {
            return $this->send($chargePointId, 'RemoteStopTransaction', ['transactionId' => (int) $payload['transaction_id']]);
        }

        throw new \RuntimeException('جلسه شارژ فعالی روی این کانکتور یافت نشد.');
    }

    private function connector(string $chargePointId, int $connectorNumber): EdgeConnector
    {
        $charger = EdgeCharger::where('charge_point_id', $chargePointId)->first();
        $connector = $charger
            ? EdgeConnector::with('charger')->where('charger_id', $charger->id)->where('connector_number', $connectorNumber)->first()
            : null;

        if (! $connector) {
            throw new \RuntimeException("کانکتور {$connectorNumber} شارژر {$chargePointId} در این ایستگاه تعریف نشده است.");
        }

        return $connector;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function chargingProfile(int $connectorNumber, array $payload): array
    {
        $maxKw = (float) ($payload['max_kw'] ?? $payload['max_power_kw'] ?? 0);
        if ($maxKw <= 0) {
            throw new \InvalidArgumentException('max_kw برای پروفایل شارژ الزامی است.');
        }

        return [
            'connectorId' => $connectorNumber,
            'csChargingProfiles' => [
                'chargingProfileId' => 1,
                'stackLevel' => 0,
                'chargingProfilePurpose' => 'TxDefaultProfile',
                'chargingProfileKind' => 'Absolute',
                'chargingSchedule' => [
                    'chargingRateUnit' => 'W',
                    'chargingSchedulePeriod' => [
                        ['startPeriod' => 0, 'limit' => round($maxKw * 1000, 1)],
                    ],
                ],
            ],
        ];
    }
}
