<?php

namespace App\Services\Sync;

use App\Models\EdgeChargingSession;
use App\Models\PendingTransaction;
use App\Models\SyncOutbox;

class SyncOutboxService
{
    /** @param  array<string, mixed>  $payload */
    public function enqueue(
        string $eventType,
        array $payload,
        ?string $aggregateType = null,
        ?string $aggregateUuid = null,
    ): SyncOutbox {
        return SyncOutbox::create([
            'event_uuid' => (string) \Illuminate\Support\Str::uuid(),
            'event_type' => $eventType,
            'aggregate_type' => $aggregateType,
            'aggregate_uuid' => $aggregateUuid ?? ($payload['uuid'] ?? null),
            'payload' => $payload,
            'occurred_at' => now(),
            'status' => 'pending',
        ]);
    }

    public function markSent(SyncOutbox $row): void
    {
        $row->update([
            'status' => 'acked',
            'acked_at' => now(),
            'last_error' => null,
        ]);

        $this->markLocalSynced($row);
    }

    public function markFailed(SyncOutbox $row, string $error): void
    {
        $attempts = $row->attempts + 1;
        $delays = [1, 2, 4, 8, 15, 30];
        $minutes = $delays[min($attempts - 1, count($delays) - 1)];

        $row->update([
            'status' => 'pending',
            'attempts' => $attempts,
            'last_error' => $error,
            'next_retry_at' => now()->addMinutes($minutes),
        ]);
    }

    private function markLocalSynced(SyncOutbox $row): void
    {
        if ($row->aggregate_type === 'charging_session' && $row->aggregate_uuid) {
            $sessionUuid = $row->aggregate_uuid;

            if ($row->event_type === 'charging.session.stopped') {
                EdgeChargingSession::where('uuid', $sessionUuid)->update([
                    'pending_sync' => false,
                    'synced_at' => now(),
                ]);
            } elseif ($row->event_type === 'charging.session.started') {
                EdgeChargingSession::where('uuid', $sessionUuid)
                    ->where('status', 'active')
                    ->update([
                        'pending_sync' => false,
                        'synced_at' => now(),
                    ]);
            }
        }

        if ($row->aggregate_type === 'wallet_transaction' && $row->aggregate_uuid) {
            PendingTransaction::where('uuid', $row->aggregate_uuid)->update([
                'pending_sync' => false,
            ]);
        }
    }
}
