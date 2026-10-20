<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\CachedUser;
use App\Models\EdgeChargingSession;
use App\Models\StationIdentity;
use App\Services\Anpr\AnprAutoStartService;
use App\Services\Anpr\ArrivalService;
use App\Services\Charging\ConnectorAuthService;
use App\Services\Charging\EdgeChargingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AppController extends Controller
{
    public function __construct(
        private EdgeChargingService $charging,
        private AnprAutoStartService $anprAutoStart,
        private ArrivalService $arrival,
        private ConnectorAuthService $auth,
    ) {}

    public function context(Request $request): JsonResponse
    {
        $station = StationIdentity::firstOrFail();
        /** @var CachedUser|null $user */
        $user = $request->attributes->get('cached_user');

        return response()->json([
            'mode' => 'edge',
            'station' => [
                'edge_code' => $station->edge_code,
                'name' => $station->name,
                'wifi_ssid' => $station->wifi_ssid,
                'local_hostname' => $station->local_hostname,
                'cloud_station_uuid' => $station->cloud_station_uuid,
            ],
            'user' => $user ? [
                'uuid' => $user->cloud_user_uuid,
                'name' => $user->name,
            ] : null,
            'sync' => [
                'last_cloud_sync_at' => $station->last_cloud_sync_at?->toIso8601String(),
                'offline_mode_enabled' => $station->offline_mode_enabled,
            ],
        ]);
    }

    public function arrival(Request $request): JsonResponse
    {
        /** @var CachedUser $user */
        $user = $request->attributes->get('cached_user');

        return response()->json($this->arrival->statusForUser($user));
    }

    public function confirmArrival(Request $request): JsonResponse
    {
        /** @var CachedUser $user */
        $user = $request->attributes->get('cached_user');

        try {
            $result = $this->arrival->confirm($user);
        } catch (\RuntimeException $e) {
            return response()->json(['ok' => false, 'message' => $e->getMessage()], 422);
        }

        return response()->json($result, ($result['ok'] ?? false) ? 200 : 202);
    }

    public function cancelArrival(Request $request): JsonResponse
    {
        /** @var CachedUser $user */
        $user = $request->attributes->get('cached_user');

        try {
            $result = $this->arrival->cancel($user);
        } catch (\RuntimeException $e) {
            return response()->json(['ok' => false, 'message' => $e->getMessage()], 422);
        }

        return response()->json($result);
    }

    public function authorizeStatus(Request $request): JsonResponse
    {
        /** @var CachedUser $user */
        $user = $request->attributes->get('cached_user');

        $connectorUuid = $request->query('connector_uuid');
        if ($connectorUuid) {
            try {
                $connector = $this->auth->resolveConnector((string) $connectorUuid);
                $grant = $this->auth->activeGrant($user, $connector);

                return response()->json([
                    'authorized' => $grant !== null,
                    'grant_type' => $grant?->grant_type,
                    'connector_uuid' => $connector->uuid,
                    'cloud_connector_uuid' => $connector->cloud_connector_uuid,
                    'expires_at' => $grant?->expires_at?->toIso8601String(),
                ]);
            } catch (\Throwable) {
                return response()->json(['authorized' => false]);
            }
        }

        return response()->json([
            'grants' => $this->auth->grantsForUser($user),
        ]);
    }

    public function authorize(Request $request): JsonResponse
    {
        /** @var CachedUser $user */
        $user = $request->attributes->get('cached_user');

        $data = $request->validate([
            'method' => ['required', 'in:qr,rfid'],
            'connector_uuid' => ['required', 'string', 'max:64'],
            'qr_token' => ['nullable', 'string', 'max:120'],
            'id_tag' => ['nullable', 'string', 'max:64'],
        ]);

        try {
            $grant = match ($data['method']) {
                'qr' => $this->auth->grantFromQr($user, $data['connector_uuid'], $data['qr_token'] ?? null),
                'rfid' => $this->auth->grantFromRfid($user, $data['connector_uuid'], (string) ($data['id_tag'] ?? '')),
            };
        } catch (\RuntimeException $e) {
            return response()->json(['ok' => false, 'message' => $e->getMessage()], 422);
        }

        return response()->json([
            'ok' => true,
            'grant_type' => $grant->grant_type,
            'expires_at' => $grant->expires_at?->toIso8601String(),
            'message' => 'مجوز شروع شارژ برای این کانکتور ثبت شد.',
        ]);
    }

    public function chargingStatus(Request $request): JsonResponse
    {
        /** @var CachedUser $user */
        $user = $request->attributes->get('cached_user');

        $session = EdgeChargingSession::with(['charger', 'connector'])
            ->where('cached_user_uuid', $user->uuid)
            ->where('status', 'active')
            ->latest('started_at')
            ->first();

        if (! $session) {
            return response()->json(['active' => false, 'source' => 'edge']);
        }

        $live = $this->charging->liveMetrics($session);

        return response()->json(array_merge([
            'active' => true,
            'source' => 'edge',
        ], $live));
    }

    public function startCharging(Request $request): JsonResponse
    {
        /** @var CachedUser $user */
        $user = $request->attributes->get('cached_user');

        $data = $request->validate([
            'connector_uuid' => ['required', 'string', 'max:64'],
        ]);

        try {
            $arrival = $this->anprAutoStart->tryStartForConnectorRequest($user, $data['connector_uuid']);
            if ($arrival?->auto_start_status === 'started' && $arrival->charging_session_uuid) {
                return response()->json([
                    'ok' => true,
                    'session_uuid' => $arrival->charging_session_uuid,
                    'source' => 'edge',
                    'auth' => 'anpr',
                    'auto_started' => true,
                ]);
            }

            $session = $this->charging->start($user, $data['connector_uuid']);

            return response()->json([
                'ok' => true,
                'session_uuid' => $session->uuid,
                'source' => 'edge',
                'auth' => $session->auth_method,
            ]);
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function stopCharging(Request $request): JsonResponse
    {
        /** @var CachedUser $user */
        $user = $request->attributes->get('cached_user');

        try {
            $session = $this->charging->stop($user);

            return response()->json([
                'ok' => true,
                'session_uuid' => $session->uuid,
                'energy_kwh' => (float) $session->energy_kwh,
                'cost_amount' => (float) $session->cost_amount,
                'source' => 'edge',
            ]);
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }
}
