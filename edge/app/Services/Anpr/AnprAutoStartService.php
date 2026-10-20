<?php

namespace App\Services\Anpr;

use App\Models\AnprDetection;
use App\Models\CachedUser;
use App\Models\EdgeChargingSession;
use App\Models\EdgeConnector;
use App\Services\Charging\ConnectorAuthService;
use App\Services\Charging\EdgeChargingService;
use App\Services\Station\StationConfigResolver;
use Illuminate\Support\Facades\Log;

class AnprAutoStartService
{
    private const ARRIVAL_TTL_MINUTES = 20;

    public function __construct(
        private AnprPlateResolver $plates,
        private EdgeChargingService $charging,
        private StationConfigResolver $config,
        private ConnectorAuthService $auth,
    ) {}

    public function processDetection(AnprDetection $detection): AnprDetection
    {
        $resolved = $this->plates->resolve($detection);
        $user = $resolved['user'];
        $vehicle = $resolved['vehicle'];

        $updates = [
            'cached_user_uuid' => $user?->uuid,
            'cached_vehicle_uuid' => $vehicle?->uuid,
        ];

        if (! $detection->connector_id) {
            $detection->update($updates);

            return $this->markFailed($detection->fresh(), 'کانکتور برای این دوربین تنظیم نشده است.');
        }

        if (! $user) {
            $detection->update($updates);

            return $this->markFailed($detection->fresh(), 'پلاک در لیست مشتریان ایستگاه یافت نشد.');
        }

        $minConf = (float) ($this->config->anpr()['min_confidence'] ?? 0.92);
        if ($detection->confidence !== null && (float) $detection->confidence < $minConf) {
            $detection->update(array_merge($updates, [
                'auto_start_status' => 'pending',
                'auto_start_message' => 'اطمینان پایین — منتظر اتصال به کانکتور',
            ]));

            if ($user && $detection->connector) {
                $this->auth->grantFromAnpr($user, $detection->connector, $detection->uuid);
            }

            return $detection->fresh();
        }

        $detection->update(array_merge($updates, [
            'auto_start_status' => 'pending',
            'auto_start_message' => 'منتظر اتصال به کانکتور '.($detection->connector?->connector_number ?? '—'),
        ]));

        if ($user && $detection->connector) {
            $this->auth->grantFromAnpr($user, $detection->connector, $detection->uuid);
        }

        return $this->tryStart($detection->fresh(), $user);
    }

    public function tryStart(AnprDetection $detection, ?CachedUser $user = null): AnprDetection
    {
        $user ??= $detection->cached_user_uuid
            ? CachedUser::where('uuid', $detection->cached_user_uuid)->first()
            : null;

        if (! $user) {
            return $this->markFailed($detection, 'کاربر برای شروع خودکار یافت نشد.');
        }

        $connector = $detection->connector_id
            ? EdgeConnector::with('charger')->find($detection->connector_id)
            : null;

        if (! $connector) {
            return $this->markFailed($detection, 'کانکتور مرتبط با دوربین یافت نشد.');
        }

        if (! $this->connectorReady($connector)) {
            return $detection;
        }

        if (EdgeChargingSession::where('cached_user_uuid', $user->uuid)->where('status', 'active')->exists()) {
            return $this->markFailed($detection, 'کاربر از قبل در حال شارژ است.');
        }

        if (EdgeChargingSession::where('connector_id', $connector->id)->where('status', 'active')->exists()) {
            return $detection;
        }

        try {
            $session = $this->charging->start($user, (string) $connector->uuid);
            $session->update([
                'auth_method' => 'anpr',
                'meta' => array_merge($session->meta ?? [], [
                    'anpr_detection_uuid' => $detection->uuid,
                    'camera_source' => $detection->camera_source,
                ]),
            ]);

            $detection->update([
                'auto_start_status' => 'started',
                'auto_start_message' => 'شارژ خودکار آغاز شد',
                'auto_start_at' => now(),
                'charging_session_uuid' => $session->uuid,
            ]);

            return $detection->fresh();
        } catch (\Throwable $e) {
            Log::warning('anpr.auto_start.failed', [
                'detection' => $detection->uuid,
                'error' => $e->getMessage(),
            ]);

            return $this->markFailed($detection, $e->getMessage());
        }
    }

    public function watchConnectors(): int
    {
        $started = 0;

        $pending = AnprDetection::with('connector')
            ->where('auto_start_status', 'pending')
            ->where('detected_at', '>=', now()->subMinutes(self::ARRIVAL_TTL_MINUTES))
            ->whereNotNull('connector_id')
            ->whereNotNull('cached_user_uuid')
            ->orderByDesc('detected_at')
            ->get();

        foreach ($pending as $detection) {
            $before = $detection->auto_start_status;
            $after = $this->tryStart($detection)->auto_start_status;
            if ($before === 'pending' && $after === 'started') {
                $started++;
            }
        }

        return $started;
    }

    public function tryStartForConnectorRequest(CachedUser $user, string $connectorUuid): ?AnprDetection
    {
        $connector = EdgeConnector::query()
            ->where(function ($q) use ($connectorUuid) {
                $q->where('uuid', $connectorUuid)
                    ->orWhere('cloud_connector_uuid', $connectorUuid);
            })
            ->first();

        if (! $connector) {
            return null;
        }

        $detection = AnprDetection::query()
            ->where('connector_id', $connector->id)
            ->where('cached_user_uuid', $user->uuid)
            ->where('auto_start_status', 'pending')
            ->where('detected_at', '>=', now()->subMinutes(self::ARRIVAL_TTL_MINUTES))
            ->latest('detected_at')
            ->first();

        if (! $detection) {
            return null;
        }

        $this->tryStart($detection, $user);

        return $detection->fresh();
    }

    private function connectorReady(EdgeConnector $connector): bool
    {
        $status = $connector->ocpp_status ?? 'Available';

        return in_array($status, ['Available', 'Preparing', 'Charging'], true);
    }

    private function markFailed(AnprDetection $detection, string $message): AnprDetection
    {
        $detection->update([
            'auto_start_status' => 'failed',
            'auto_start_message' => $message,
            'auto_start_at' => now(),
        ]);

        return $detection->fresh();
    }
}
