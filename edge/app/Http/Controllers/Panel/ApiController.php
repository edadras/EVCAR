<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Models\EdgeChargingSession;
use App\Services\Charging\EdgeChargingService;
use App\Services\Panel\PanelCommandService;
use App\Services\Panel\PanelLiveService;
use App\Services\Panel\PanelSyncService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ApiController extends Controller
{
    public function __construct(
        private PanelLiveService $live,
        private PanelCommandService $commands,
        private PanelSyncService $sync,
        private EdgeChargingService $charging,
    ) {}

    public function live(): JsonResponse
    {
        $live = $this->live->liveConnectors();

        return response()->json([
            'online' => $live['online'],
            'cloud_online' => $this->live->cloudReachable(),
            'connectors' => $live['connectors'],
        ]);
    }

    public function storeCommand(Request $request): JsonResponse
    {
        $data = $request->validate([
            'command_type' => ['required', 'string', 'max:40'],
            'connector_id' => ['nullable', 'integer'],
            'payload' => ['nullable', 'array'],
        ]);

        try {
            $log = $this->commands->execute(
                $request->user(),
                $data['command_type'],
                $data['connector_id'] ?? null,
                $data['payload'] ?? [],
            );
        } catch (\Throwable $e) {
            return response()->json(['ok' => false, 'error' => $e->getMessage()], 422);
        }

        return response()->json([
            'ok' => true,
            'uuid' => $log->uuid,
            'status' => $log->status,
            'simulated' => $log->simulated,
            'message' => $log->simulated
                ? 'دستور به‌صورت شبیه‌سازی ثبت شد (OCPP Gateway تنظیم نشده).'
                : 'دستور محلی روی OCPP اجرا شد.',
        ]);
    }

    public function recentCommands(Request $request): JsonResponse
    {
        $source = $request->query('source');

        return response()->json(['commands' => $this->commands->recent(30, $source ?: null)]);
    }

    public function syncStatus(): JsonResponse
    {
        return response()->json($this->sync->status());
    }

    public function syncFeed(): JsonResponse
    {
        return response()->json([
            'status' => $this->sync->status(),
            'outbox' => $this->sync->outboxList(20),
            'activity' => $this->sync->activityLogs(15),
            'commands_cloud' => $this->commands->recent(10, 'cloud'),
            'commands_panel' => $this->commands->recent(10, 'panel'),
        ]);
    }

    public function syncOutbox(Request $request): JsonResponse
    {
        return response()->json([
            'items' => $this->sync->outboxList(50, $request->query('filter')),
        ]);
    }

    public function syncCached(): JsonResponse
    {
        return response()->json([
            'summary' => $this->sync->cachedSummary(),
            'users' => $this->sync->cachedUsers(40),
            'pending_sessions' => $this->sync->pendingSessions(20),
        ]);
    }

    public function syncActivity(): JsonResponse
    {
        return response()->json(['items' => $this->sync->activityLogs(50)]);
    }

    public function syncPull(Request $request): JsonResponse
    {
        if ($request->boolean('full')) {
            $result = $this->sync->pullSnapshot();

            return response()->json($result, $result['ok'] ? 200 : 422);
        }

        if ($request->boolean('now')) {
            $result = $this->sync->runNow();

            return response()->json($result, $result['ok'] ? 200 : 422);
        }

        $result = $this->sync->pullDelta();

        return response()->json($result, $result['ok'] ? 200 : 422);
    }

    public function syncPush(): JsonResponse
    {
        $result = $this->sync->pushOutbox();

        return response()->json($result, $result['ok'] ? 200 : 422);
    }

    public function syncPullCommands(): JsonResponse
    {
        $result = $this->sync->pullCommands();

        return response()->json($result, $result['ok'] ? 200 : 422);
    }

    public function stopSession(string $sessionUuid): JsonResponse
    {
        $session = EdgeChargingSession::where('uuid', $sessionUuid)->where('status', 'active')->first();
        if (! $session) {
            return response()->json(['ok' => false, 'error' => 'جلسه فعال یافت نشد.'], 404);
        }

        try {
            $stopped = $this->charging->stopSession($session, 'Local');
        } catch (\Throwable $e) {
            return response()->json(['ok' => false, 'error' => $e->getMessage()], 422);
        }

        return response()->json([
            'ok' => true,
            'session_uuid' => $stopped->uuid,
            'energy_kwh' => (float) $stopped->energy_kwh,
            'cost_amount' => (float) $stopped->cost_amount,
        ]);
    }
}
