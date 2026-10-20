<?php

namespace App\Services\Ocpp;

/**
 * تبدیل نوع دستور مدیریتی به پیام OCPP 1.6J.
 */
final class OcppCommandMap
{
    /**
     * @param  array<string, mixed>  $payload
     * @return array{0:string,1:array<string, mixed>}
     */
    public static function build(string $type, int $connectorNumber, array $payload): array
    {
        return match ($type) {
            'reset' => ['Reset', ['type' => ($payload['type'] ?? 'Soft') === 'Hard' ? 'Hard' : 'Soft']],
            'unlock' => ['UnlockConnector', ['connectorId' => $connectorNumber]],
            'change_availability' => ['ChangeAvailability', [
                'connectorId' => $connectorNumber,
                'type' => ($payload['type'] ?? 'Inoperative') === 'Operative' ? 'Operative' : 'Inoperative',
            ]],
            'set_charging_profile' => ['SetChargingProfile', self::chargingProfile($connectorNumber, (float) ($payload['max_kw'] ?? $payload['max_power_kw'] ?? 0), 'TxDefaultProfile', 1)],
            'clear_charging_profile' => ['ClearChargingProfile', ['connectorId' => $connectorNumber]],
            'get_diagnostics' => ['GetDiagnostics', ['location' => (string) ($payload['location'] ?? 'ftp://diagnostics/')]],
            'update_firmware' => ['UpdateFirmware', [
                'location' => self::required($payload, 'location', 'آدرس فایل Firmware (location) الزامی است.'),
                'retrieveDate' => (string) ($payload['retrieveDate'] ?? now()->utc()->format('Y-m-d\TH:i:s\Z')),
            ]],
            'trigger_status' => ['TriggerMessage', ['requestedMessage' => 'StatusNotification', 'connectorId' => $connectorNumber]],
            'get_configuration' => ['GetConfiguration', isset($payload['key']) ? ['key' => array_values((array) $payload['key'])] : []],
            'change_configuration' => ['ChangeConfiguration', [
                'key' => self::required($payload, 'key', 'کلید تنظیمات الزامی است.'),
                'value' => (string) ($payload['value'] ?? ''),
            ]],
            'reserve_now' => ['ReserveNow', [
                'connectorId' => $connectorNumber,
                'expiryDate' => (string) ($payload['expiryDate'] ?? now()->addMinutes(15)->utc()->format('Y-m-d\TH:i:s\Z')),
                'idTag' => self::required($payload, 'idTag', 'idTag برای رزرو الزامی است.'),
                'reservationId' => (int) ($payload['reservationId'] ?? random_int(1, 2_000_000_000)),
            ]],
            'cancel_reservation' => ['CancelReservation', ['reservationId' => (int) self::required($payload, 'reservationId', 'شناسه رزرو الزامی است.')]],
            default => throw new \InvalidArgumentException('نوع دستور ناشناخته: '.$type),
        };
    }

    /** @return array<string, mixed> */
    public static function chargingProfile(int $connectorNumber, float $maxKw, string $purpose, int $profileId, int $stackLevel = 0): array
    {
        if ($maxKw <= 0) {
            throw new \InvalidArgumentException('max_kw برای پروفایل شارژ الزامی است.');
        }

        return [
            'connectorId' => $connectorNumber,
            'csChargingProfiles' => [
                'chargingProfileId' => $profileId,
                'stackLevel' => $stackLevel,
                'chargingProfilePurpose' => $purpose,
                'chargingProfileKind' => 'Absolute',
                'chargingSchedule' => [
                    'chargingRateUnit' => 'W',
                    'chargingSchedulePeriod' => [['startPeriod' => 0, 'limit' => round($maxKw * 1000, 1)]],
                ],
            ],
        ];
    }

    /** @param array<string, mixed> $payload */
    private static function required(array $payload, string $key, string $message): string
    {
        $value = (string) ($payload[$key] ?? '');
        if ($value === '') {
            throw new \InvalidArgumentException($message);
        }

        return $value;
    }
}
