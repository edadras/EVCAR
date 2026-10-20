<?php

namespace App\Services\Anpr;

use App\Models\AnprDetection;
use App\Models\CachedUser;
use App\Models\StationIdentity;
use App\Services\Charging\ConnectorAuthService;
use App\Services\CloudSyncClient;
use App\Support\Persian;

class ArrivalService
{
    private const TTL_MINUTES = 20;

    public function __construct(
        private AnprAutoStartService $autoStart,
        private ConnectorAuthService $auth,
        private CloudSyncClient $cloud,
    ) {}

    /** @return array<string, mixed> */
    public function statusForUser(CachedUser $user): array
    {
        $station = StationIdentity::first();
        $detection = $this->pendingDetection($user);

        if (! $detection) {
            return [
                'pending' => false,
                'station' => $station ? ['name' => $station->name, 'edge_code' => $station->edge_code] : null,
            ];
        }

        $detection->loadMissing('connector.charger');
        $connector = $detection->connector;
        $expiresAt = $detection->detected_at?->copy()->addMinutes(self::TTL_MINUTES);

        return [
            'pending' => true,
            'detection_uuid' => $detection->uuid,
            'auto_start_status' => $detection->auto_start_status,
            'auto_start_message' => $detection->auto_start_message,
            'plate' => $this->formatPlate($detection),
            'confidence' => $detection->confidence,
            'expires_at' => $expiresAt?->toIso8601String(),
            'expires_in_sec' => $expiresAt ? (int) max(0, now()->diffInSeconds($expiresAt, false)) : 0,
            'station' => [
                'name' => $station?->name,
                'edge_code' => $station?->edge_code,
            ],
            'connector' => $connector ? [
                'uuid' => $connector->uuid,
                'cloud_uuid' => $connector->cloud_connector_uuid,
                'number' => $connector->connector_number,
                'charge_point_id' => $connector->charger?->charge_point_id,
                'label' => ($connector->charger?->charge_point_id ?? 'CP').' · کانکتور '.$connector->connector_number,
                'ocpp_status' => $connector->ocpp_status,
            ] : null,
            'camera' => $detection->camera_source,
            'charging_session_uuid' => $detection->charging_session_uuid,
            'can_confirm' => $detection->auto_start_status === 'pending' && $connector !== null,
            'can_cancel' => $detection->auto_start_status === 'pending',
        ];
    }

    /** @return array<string, mixed> */
    public function cancel(CachedUser $user): array
    {
        $detection = $this->pendingDetection($user);
        if (! $detection) {
            throw new \RuntimeException('ورود فعالی برای لغو یافت نشد.');
        }

        if ($detection->auto_start_status === 'started') {
            throw new \RuntimeException('شارژ آغاز شده — برای توقف از صفحه شارژ استفاده کنید.');
        }

        $connectorId = $detection->connector_id;
        $detection->update([
            'auto_start_status' => 'cancelled',
            'auto_start_message' => 'لغو توسط کاربر',
            'auto_start_at' => now(),
        ]);

        $this->auth->revokeAllForUser($user, $connectorId);

        $fresh = $detection->fresh();
        $fresh->update(['pending_sync' => true]);
        $this->cloud->pushAnprDetection($fresh);

        return [
            'ok' => true,
            'message' => 'ورود لغو شد. می‌توانید کانکتور دیگری را با اسکن QR یا RFID انتخاب کنید.',
        ];
    }

    /** @return array<string, mixed> */
    public function confirm(CachedUser $user): array
    {
        $detection = $this->pendingDetection($user);
        if (! $detection) {
            throw new \RuntimeException('ورود فعالی برای تأیید یافت نشد.');
        }

        $result = $this->autoStart->tryStart($detection->fresh(), $user);
        $result->loadMissing('connector.charger');

        if ($result->auto_start_status === 'started' && $result->charging_session_uuid) {
            return [
                'ok' => true,
                'session_uuid' => $result->charging_session_uuid,
                'message' => 'شارژ آغاز شد.',
            ];
        }

        if ($result->auto_start_status === 'pending') {
            return [
                'ok' => false,
                'waiting_connector' => true,
                'message' => 'کابل را به کانکتور وصل کنید — سیستم به‌محض آماده‌شدن شارژ را شروع می‌کند.',
            ];
        }

        throw new \RuntimeException($result->auto_start_message ?? 'شروع شارژ ممکن نیست.');
    }

    private function pendingDetection(CachedUser $user): ?AnprDetection
    {
        return AnprDetection::with('connector.charger')
            ->where('cached_user_uuid', $user->uuid)
            ->whereIn('auto_start_status', ['pending', 'started'])
            ->where('detected_at', '>=', now()->subMinutes(self::TTL_MINUTES))
            ->latest('detected_at')
            ->first();
    }

    private function formatPlate(AnprDetection $d): string
    {
        if ($d->plate_two && $d->plate_letter && $d->plate_three) {
            return Persian::digits($d->plate_two).$d->plate_letter.Persian::digits($d->plate_three)
                .($d->plate_province ? ' · '.Persian::digits($d->plate_province) : '');
        }

        return $d->plate_normalized ?? '—';
    }
}
