<?php

namespace App\Services\Ocpp;

use App\Models\EdgeCharger;
use App\Models\EdgeChargingSession;
use App\Models\EdgeConnector;
use Illuminate\Support\Facades\DB;

class EdgeCommandExecutor
{
    public function __construct(private OcppGatewayClient $gateway) {}

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

        if (! $this->gateway->configured()) {
            return [
                'simulated' => true,
                'command_type' => $type,
                'charge_point_id' => $chargePointId,
                'message' => 'OCPP Gateway تنظیم نشده — دستور به‌صورت شبیه‌سازی ثبت شد.',
            ];
        }

        return match ($type) {
            'reset' => $this->gateway->send($chargePointId, 'Reset', [
                'type' => $payload['type'] ?? 'Soft',
            ]),
            'unlock' => $this->gateway->send($chargePointId, 'UnlockConnector', [
                'connectorId' => $connectorNumber,
            ]),
            'remote_stop' => $this->remoteStop($chargePointId, $connectorNumber, $payload),
            'change_availability' => $this->gateway->send($chargePointId, 'ChangeAvailability', [
                'connectorId' => $connectorNumber,
                'type' => $payload['type'] ?? 'Inoperative',
            ]),
            'set_charging_profile' => $this->setChargingProfile($chargePointId, $connectorNumber, $payload),
            'get_diagnostics' => $this->gateway->send($chargePointId, 'GetDiagnostics', $payload ?: [
                'location' => 'ftp://diagnostics/',
            ]),
            'update_firmware' => $this->gateway->send($chargePointId, 'UpdateFirmware', $payload ?: [
                'location' => 'http://firmware.local/update.bin',
                'retrieveDate' => now()->addHour()->toIso8601String(),
            ]),
            default => throw new \InvalidArgumentException('نوع دستور ناشناخته: '.$type),
        };
    }

    /** @param array<string, mixed> $payload */
    private function remoteStop(string $chargePointId, int $connectorNumber, array $payload): array
    {
        if (! empty($payload['transaction_id'])) {
            return $this->gateway->send($chargePointId, 'RemoteStopTransaction', [
                'transactionId' => (int) $payload['transaction_id'],
            ]);
        }

        $charger = EdgeCharger::where('charge_point_id', $chargePointId)->first();
        $connector = $charger
            ? EdgeConnector::where('charger_id', $charger->id)->where('connector_number', $connectorNumber)->first()
            : null;

        $session = null;
        if ($connector) {
            $session = EdgeChargingSession::where('connector_id', $connector->id)
                ->where('status', 'active')
                ->latest('started_at')
                ->first();
        }

        $txId = $session ? DB::table('charging_sessions')->where('id', $session->id)->value('ocpp_transaction_id') : null;
        if ($txId) {
            return $this->gateway->send($chargePointId, 'RemoteStopTransaction', [
                'transactionId' => (int) $txId,
            ]);
        }

        $gatewayChargerId = (int) ($charger?->meta['ocpp_gateway_charger_id'] ?? 0);
        if ($gatewayChargerId > 0) {
            return $this->gateway->remoteStopCharger($gatewayChargerId);
        }

        throw new \RuntimeException('شناسه تراکنش OCPP برای توقف شارژ یافت نشد.');
    }

    /** @param array<string, mixed> $payload */
    private function setChargingProfile(string $chargePointId, int $connectorNumber, array $payload): array
    {
        $maxKw = (int) ($payload['max_kw'] ?? $payload['max_power_kw'] ?? 0);
        if ($maxKw < 1) {
            throw new \InvalidArgumentException('max_kw برای پروفایل شارژ الزامی است.');
        }

        $watts = $maxKw * 1000;

        return $this->gateway->send($chargePointId, 'SetChargingProfile', [
            'connectorId' => $connectorNumber,
            'csChargingProfiles' => [
                'chargingProfileId' => 1,
                'stackLevel' => 0,
                'chargingProfilePurpose' => 'TxDefaultProfile',
                'chargingProfileKind' => 'Absolute',
                'chargingSchedule' => [
                    'chargingRateUnit' => 'W',
                    'chargingSchedulePeriod' => [
                        ['startPeriod' => 0, 'limit' => $watts],
                    ],
                ],
            ],
        ]);
    }
}
