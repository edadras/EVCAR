<?php

namespace App\Services\Sync;

use App\Models\SyncOutbox;
use App\Services\CloudSyncClient;

class SyncPushRunner
{
    public function __construct(
        private CloudSyncClient $client,
        private SyncOutboxService $outbox,
        private SessionSyncReconciler $reconciler,
    ) {}

    /** @return array{pushed:int, failed:int, accepted:int} */
    public function pushPending(int $limit = 50): array
    {
        $rows = SyncOutbox::query()
            ->where('status', 'pending')
            ->where(function ($q) {
                $q->whereNull('next_retry_at')->orWhere('next_retry_at', '<=', now());
            })
            ->orderBy('occurred_at')
            ->limit(max(1, $limit))
            ->get();

        if ($rows->isEmpty()) {
            return ['pushed' => 0, 'failed' => 0, 'accepted' => 0];
        }

        $events = $rows->map(fn (SyncOutbox $row) => [
            'event_uuid' => $row->event_uuid,
            'event_type' => $row->event_type,
            'occurred_at' => $row->occurred_at?->toIso8601String(),
            'payload' => $row->payload,
        ])->all();

        $result = $this->client->pushEvents($events);
        if ($result === null) {
            foreach ($rows as $row) {
                $this->outbox->markFailed($row, 'Cloud push failed');
            }

            return ['pushed' => 0, 'failed' => $rows->count(), 'accepted' => 0];
        }

        $failedUuids = collect($result['errors'] ?? [])
            ->pluck('event_uuid')
            ->filter()
            ->flip();

        $pushed = 0;
        $failed = 0;
        foreach ($rows as $row) {
            if ($failedUuids->has($row->event_uuid)) {
                $msg = collect($result['errors'])->firstWhere('event_uuid', $row->event_uuid)['message'] ?? 'ingest error';
                $this->outbox->markFailed($row, $msg);
                $failed++;
            } else {
                $this->outbox->markSent($row);
                $pushed++;
            }
        }

        $reconcile = $this->reconciler->reconcile();

        return [
            'pushed' => $pushed,
            'failed' => $failed,
            'accepted' => (int) ($result['accepted'] ?? 0),
            'reconciled_sessions' => $reconcile['sessions'],
        ];
    }
}
