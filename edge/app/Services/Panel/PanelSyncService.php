<?php

namespace App\Services\Panel;

use App\Models\CachedTariff;
use App\Models\CachedUser;
use App\Models\CachedWallet;
use App\Models\EdgeCharger;
use App\Models\EdgeChargingSession;
use App\Models\StationIdentity;
use App\Models\SyncActivityLog;
use App\Models\SyncCursor;
use App\Models\SyncOutbox;
use App\Services\Sync\CloudCommandPullService;
use App\Services\Sync\CloudConnectivity;
use App\Services\Sync\CloudPullService;
use App\Services\Sync\SyncOrchestrator;
use App\Services\Sync\SyncPushRunner;
use Illuminate\Support\Facades\Artisan;

class PanelSyncService
{
    public function __construct(
        private CloudPullService $pull,
        private SyncPushRunner $push,
        private CloudCommandPullService $commandPull,
        private SyncOrchestrator $orchestrator,
        private CloudConnectivity $connectivity,
        private PanelLiveService $live,
    ) {}

    /** @return array<string, mixed> */
    public function status(): array
    {
        $station = StationIdentity::first();
        $cursor = SyncCursor::where('stream', 'cloud_pull')->first();
        $pending = SyncOutbox::where('status', 'pending')->whereNull('last_error')->count();
        $retry = SyncOutbox::where('status', 'pending')->whereNotNull('last_error')->count();
        $acked = SyncOutbox::where('status', 'acked')->count();

        return [
            'station' => [
                'name' => $station?->name,
                'edge_code' => $station?->edge_code,
                'offline_mode_enabled' => (bool) ($station?->offline_mode_enabled ?? true),
                'software_version' => $station?->software_version,
            ],
            'cloud' => [
                'reachable' => $this->live->cloudReachable(),
                'last_pull_at' => $cursor?->last_synced_at?->toIso8601String(),
                'last_push_at' => $station?->last_cloud_sync_at?->toIso8601String(),
                'last_heartbeat_at' => $station?->last_cloud_heartbeat_at?->toIso8601String(),
            ],
            'auto_sync' => [
                'enabled' => true,
                'interval' => 'هر ۱ دقیقه',
                'mode' => $cursor?->last_synced_at ? 'delta' : 'snapshot_first',
                'next_retry_at' => $this->connectivity->status()['next_retry_at'],
                'last_auto_at' => SyncActivityLog::where('action', 'auto_sync')
                    ->whereIn('status', ['completed', 'skipped'])
                    ->latest('id')
                    ->value('created_at')?->toIso8601String(),
            ],
            'outbox' => [
                'pending' => $pending,
                'retry' => $retry,
                'failed' => $retry,
                'acked' => $acked,
                'total' => SyncOutbox::count(),
            ],
            'cached' => $this->cachedSummary(),
            'local_pending' => $this->localPendingSummary(),
        ];
    }

    /** @return array<string, int> */
    public function cachedSummary(): array
    {
        return [
            'users' => CachedUser::count(),
            'users_local_only' => CachedUser::where('is_local_only', true)->count(),
            'users_pending' => CachedUser::where('pending_sync', true)->count(),
            'wallets' => CachedWallet::count(),
            'tariffs' => CachedTariff::count(),
            'chargers' => EdgeCharger::count(),
        ];
    }

    /** @return array<string, int> */
    public function localPendingSummary(): array
    {
        return [
            'sessions' => EdgeChargingSession::where('pending_sync', true)->count(),
            'sessions_total' => EdgeChargingSession::count(),
        ];
    }

    /** @return list<array<string, mixed>> */
    public function outboxList(int $limit = 40, ?string $filter = null): array
    {
        $q = SyncOutbox::query()->orderByDesc('occurred_at')->limit($limit);

        if ($filter === 'pending') {
            $q->where('status', 'pending')->whereNull('last_error');
        } elseif ($filter === 'retry') {
            $q->where('status', 'pending')->whereNotNull('last_error');
        } elseif ($filter === 'acked') {
            $q->where('status', 'acked');
        }

        return $q->get()->map(fn (SyncOutbox $row) => [
            'event_uuid' => $row->event_uuid,
            'event_type' => $row->event_type,
            'aggregate_type' => $row->aggregate_type,
            'aggregate_uuid' => $row->aggregate_uuid,
            'status' => $row->status,
            'attempts' => $row->attempts,
            'last_error' => $row->last_error,
            'occurred_at' => $row->occurred_at?->toIso8601String(),
            'acked_at' => $row->acked_at?->toIso8601String(),
            'payload_preview' => $this->payloadPreview($row->payload),
        ])->all();
    }

    /** @return list<array<string, mixed>> */
    public function cachedUsers(int $limit = 30): array
    {
        return CachedUser::with('wallet')
            ->orderByDesc('updated_at')
            ->limit($limit)
            ->get()
            ->map(fn (CachedUser $u) => [
                'uuid' => $u->uuid,
                'name' => $u->name,
                'phone' => $u->phone,
                'status' => $u->status,
                'is_local_only' => $u->is_local_only,
                'pending_sync' => $u->pending_sync,
                'synced_at' => $u->synced_at?->toIso8601String(),
                'wallet_balance' => (float) ($u->wallet?->balance ?? 0),
            ])
            ->all();
    }

    /** @return list<array<string, mixed>> */
    public function pendingSessions(int $limit = 20): array
    {
        return EdgeChargingSession::where('pending_sync', true)
            ->orderByDesc('started_at')
            ->limit($limit)
            ->get()
            ->map(fn (EdgeChargingSession $s) => [
                'uuid' => $s->uuid,
                'status' => $s->status,
                'energy_kwh' => (float) $s->energy_kwh,
                'cost_amount' => (float) $s->cost_amount,
                'started_at' => $s->started_at?->toIso8601String(),
                'synced_at' => $s->synced_at?->toIso8601String(),
            ])
            ->all();
    }

    /** @return list<array<string, mixed>> */
    public function activityLogs(int $limit = 30): array
    {
        return SyncActivityLog::orderByDesc('id')
            ->limit($limit)
            ->get()
            ->map(fn (SyncActivityLog $log) => [
                'id' => $log->id,
                'action' => $log->action,
                'action_label' => $this->actionLabel($log->action),
                'status' => $log->status,
                'stats' => $log->stats,
                'message' => $log->message,
                'error' => $log->error,
                'at' => $log->created_at?->toIso8601String(),
            ])
            ->all();
    }

    /** @return array<string, mixed> */
    public function runNow(): array
    {
        $result = $this->orchestrator->run();

        return ['ok' => ! isset($result['error']), 'result' => $result];
    }

    /** @return array<string, mixed> */
    public function pullDelta(): array
    {
        try {
            $result = $this->pull->pull();
            $this->logActivity('pull_delta', 'completed', $result['stats'] ?? [], 'دریافت Delta از مرکز');

            return ['ok' => true, 'result' => $result];
        } catch (\Throwable $e) {
            $this->logActivity('pull_delta', 'failed', null, null, $e->getMessage());

            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }

    /** @return array<string, mixed> */
    public function pullSnapshot(): array
    {
        try {
            Artisan::call('sync:pull-snapshot');
            $this->logActivity('pull_snapshot', 'completed', null, 'اسنپ‌شات کامل از مرکز دریافت شد');

            return ['ok' => true, 'message' => 'اسنپ‌شات کامل دریافت شد.'];
        } catch (\Throwable $e) {
            $this->logActivity('pull_snapshot', 'failed', null, null, $e->getMessage());

            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }

    /** @return array<string, mixed> */
    public function pushOutbox(int $limit = 50): array
    {
        try {
            $result = $this->push->pushPending($limit);
            $msg = sprintf('ارسال: %d موفق، %d خطا', $result['pushed'] ?? 0, $result['failed'] ?? 0);
            $this->logActivity('push_outbox', ($result['failed'] ?? 0) > 0 && ($result['pushed'] ?? 0) === 0 ? 'failed' : 'completed', $result, $msg);

            return ['ok' => true, 'result' => $result];
        } catch (\Throwable $e) {
            $this->logActivity('push_outbox', 'failed', null, null, $e->getMessage());

            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }

    /** @return array<string, mixed> */
    public function pullCommands(): array
    {
        try {
            $stats = $this->commandPull->pullAndExecute();
            $msg = sprintf('دستورات ابری: %d دریافت، %d اجرا، %d خطا', $stats['pulled'], $stats['ok'], $stats['failed']);
            $this->logActivity('pull_commands', $stats['failed'] > 0 ? 'partial' : 'completed', $stats, $msg);

            return ['ok' => true, 'message' => $msg, 'stats' => $stats];
        } catch (\Throwable $e) {
            $this->logActivity('pull_commands', 'failed', null, null, $e->getMessage());

            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }

    /** @param array<string, mixed>|null $stats */
    private function logActivity(string $action, string $status, ?array $stats = null, ?string $message = null, ?string $error = null): void
    {
        SyncActivityLog::create([
            'action' => $action,
            'status' => $status,
            'stats' => $stats,
            'message' => $message,
            'error' => $error,
        ]);
    }

    private function actionLabel(string $action): string
    {
        return match ($action) {
            'pull_delta' => 'دریافت Delta',
            'pull_snapshot' => 'اسنپ‌شات کامل',
            'push_outbox' => 'ارسال صف به ابر',
            'pull_commands' => 'دستورات مرکز',
            'auto_sync' => 'سینک خودکار',
            default => $action,
        };
    }

    /** @param array<string, mixed>|null $payload */
    private function payloadPreview(?array $payload): string
    {
        if (! $payload) {
            return '—';
        }

        $keys = ['uuid', 'session_uuid', 'user_uuid', 'phone', 'amount', 'energy_kwh'];
        $parts = [];
        foreach ($keys as $key) {
            if (isset($payload[$key])) {
                $parts[] = "{$key}=".((string) $payload[$key]);
            }
        }

        return $parts ? implode(' · ', $parts) : json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
