<?php

namespace App\Services\Sync;

use App\Models\AnprDetection;
use App\Models\StationIdentity;
use App\Models\SyncActivityLog;
use App\Services\CloudSyncClient;
use Illuminate\Support\Facades\Log;

class SyncOrchestrator
{
    private const PUSH_BATCH_SIZE = 50;

    private const MAX_PUSH_BATCHES = 6;

    public function __construct(
        private CloudConnectivity $connectivity,
        private SyncPushRunner $push,
        private CloudPullService $pull,
        private CloudCommandPullService $commands,
        private CloudSyncClient $anprClient,
        private SessionSyncReconciler $reconciler,
    ) {}

    /** @return array<string, mixed> */
    public function run(): array
    {
        $result = [
            'skipped' => false,
            'reason' => null,
            'reconcile' => $this->reconciler->reconcile(),
            'push' => ['pushed' => 0, 'failed' => 0, 'accepted' => 0, 'batches' => 0],
            'pull' => null,
            'commands' => null,
            'anpr' => ['pushed' => 0, 'pending' => 0],
        ];

        if (! $this->connectivity->canAttempt('cloud_link')) {
            $result['skipped'] = true;
            $result['reason'] = 'backoff';

            return $result;
        }

        if (! $this->connectivity->isReachable()) {
            $this->connectivity->recordFailure('cloud_link', 'مرکز ابری در دسترس نیست');
            $this->logThrottled('auto_sync', 'skipped', null, 'مرکز آفلاین — تلاش مجدد خودکار');

            $result['skipped'] = true;
            $result['reason'] = 'cloud_offline';

            return $result;
        }

        try {
            $result['push'] = $this->pushAllPending();
            $result['reconcile'] = $this->reconciler->reconcile();
            $result['pull'] = $this->pullDelta();
            $result['commands'] = $this->pullCommands();
            $result['anpr'] = $this->pushAnpr();

            $this->touchHeartbeat();
            $this->connectivity->recordSuccess('cloud_link');

            $this->logActivity('auto_sync', 'completed', [
                'push' => $result['push'],
                'pull' => $result['pull'],
                'commands' => $result['commands'],
                'anpr' => $result['anpr'],
            ], $this->summaryMessage($result));
        } catch (\Throwable $e) {
            $this->connectivity->recordFailure('cloud_link', $e->getMessage());
            $this->logActivity('auto_sync', 'failed', null, null, $e->getMessage());
            Log::warning('sync.auto.failed', ['error' => $e->getMessage()]);

            $result['skipped'] = false;
            $result['reason'] = 'error';
            $result['error'] = $e->getMessage();
        }

        return $result;
    }

    /** @return array{pushed:int,failed:int,accepted:int,batches:int} */
    private function pushAllPending(): array
    {
        $totals = ['pushed' => 0, 'failed' => 0, 'accepted' => 0, 'batches' => 0];

        for ($i = 0; $i < self::MAX_PUSH_BATCHES; $i++) {
            $batch = $this->push->pushPending(self::PUSH_BATCH_SIZE);
            $totals['pushed'] += $batch['pushed'];
            $totals['failed'] += $batch['failed'];
            $totals['accepted'] += $batch['accepted'];
            $totals['batches']++;

            if ($batch['pushed'] === 0 && $batch['failed'] === 0) {
                break;
            }

            if ($batch['failed'] > 0 && $batch['pushed'] === 0) {
                break;
            }
        }

        return $totals;
    }

    /** @return array<string, mixed> */
    private function pullDelta(): array
    {
        $pull = $this->pull->pull();

        return [
            'mode' => $pull['mode'],
            'stats' => $pull['stats'],
        ];
    }

    /** @return array{pulled:int,ok:int,failed:int} */
    private function pullCommands(): array
    {
        return $this->commands->pullAndExecute();
    }

    /** @return array{pushed:int,pending:int} */
    private function pushAnpr(): array
    {
        $pending = AnprDetection::query()
            ->where('pending_sync', true)
            ->orderBy('detected_at')
            ->limit(30)
            ->get();

        $pushed = 0;
        foreach ($pending as $detection) {
            if ($this->anprClient->pushAnprDetection($detection)) {
                $pushed++;
            }
        }

        return [
            'pushed' => $pushed,
            'pending' => AnprDetection::where('pending_sync', true)->count(),
        ];
    }

    private function touchHeartbeat(): void
    {
        $station = StationIdentity::first();
        if ($station) {
            $station->update(['last_cloud_heartbeat_at' => now()]);
        }
    }

    /** @param array<string, mixed> $result */
    private function summaryMessage(array $result): string
    {
        $push = $result['push'];
        $pull = $result['pull'];
        $cmds = $result['commands'];

        return sprintf(
            'خودکار: ارسال %d · دریافت %s · دستور %d/%d',
            $push['pushed'] ?? 0,
            $pull['mode'] ?? '—',
            $cmds['ok'] ?? 0,
            $cmds['pulled'] ?? 0,
        );
    }

    /** @param array<string, mixed>|null $stats */
    private function logActivity(string $action, string $status, ?array $stats, ?string $message = null, ?string $error = null): void
    {
        SyncActivityLog::create([
            'action' => $action,
            'status' => $status,
            'stats' => $stats,
            'message' => $message,
            'error' => $error,
        ]);
    }

    /** @param array<string, mixed>|null $stats */
    private function logThrottled(string $action, string $status, ?array $stats, ?string $message): void
    {
        $recent = SyncActivityLog::where('action', $action)
            ->where('message', $message)
            ->where('created_at', '>=', now()->subMinutes(5))
            ->exists();

        if (! $recent) {
            $this->logActivity($action, $status, $stats, $message);
        }
    }
}
