<?php

namespace App\Services\Sync;

use App\Models\EdgeChargingSession;
use App\Models\PendingTransaction;
use App\Models\SyncOutbox;
use Illuminate\Support\Facades\DB;

class SessionSyncReconciler
{
    /** @return array{sessions:int,transactions:int} */
    public function reconcile(): array
    {
        $sessions = $this->reconcileSessions();
        $transactions = $this->reconcileTransactions();

        return compact('sessions', 'transactions');
    }

    private function reconcileSessions(): int
    {
        $fixed = 0;

        EdgeChargingSession::query()
            ->where('pending_sync', true)
            ->orderBy('id')
            ->chunkById(100, function ($sessions) use (&$fixed) {
                foreach ($sessions as $session) {
                    if ($this->sessionFullySynced($session)) {
                        $session->update([
                            'pending_sync' => false,
                            'synced_at' => $session->synced_at ?? now(),
                        ]);
                        $fixed++;
                    }
                }
            });

        return $fixed;
    }

    private function reconcileTransactions(): int
    {
        return PendingTransaction::query()
            ->where('pending_sync', true)
            ->whereNotExists(function ($q) {
                $q->select(DB::raw(1))
                    ->from('sync_outbox')
                    ->whereColumn('sync_outbox.aggregate_uuid', 'pending_transactions.uuid')
                    ->where('sync_outbox.aggregate_type', 'wallet_transaction')
                    ->where('sync_outbox.status', 'pending');
            })
            ->whereExists(function ($q) {
                $q->select(DB::raw(1))
                    ->from('sync_outbox')
                    ->whereColumn('sync_outbox.aggregate_uuid', 'pending_transactions.uuid')
                    ->where('sync_outbox.aggregate_type', 'wallet_transaction')
                    ->where('sync_outbox.status', 'acked');
            })
            ->update(['pending_sync' => false]);
    }

    private function sessionFullySynced(EdgeChargingSession $session): bool
    {
        if ($this->hasPendingSessionEvents($session->uuid)) {
            return false;
        }

        if ($session->status === 'stopped') {
            return $this->hasAckedEvent('charging.session.stopped', $session->uuid);
        }

        if ($session->status === 'active') {
            return $this->hasAckedEvent('charging.session.started', $session->uuid);
        }

        return $this->hasAckedEvent('charging.session.stopped', $session->uuid)
            || $this->hasAckedEvent('charging.session.started', $session->uuid);
    }

    private function hasPendingSessionEvents(string $sessionUuid): bool
    {
        return SyncOutbox::query()
            ->where('aggregate_type', 'charging_session')
            ->where('aggregate_uuid', $sessionUuid)
            ->where('status', 'pending')
            ->exists();
    }

    private function hasAckedEvent(string $eventType, string $sessionUuid): bool
    {
        return SyncOutbox::query()
            ->where('aggregate_type', 'charging_session')
            ->where('aggregate_uuid', $sessionUuid)
            ->where('event_type', $eventType)
            ->where('status', 'acked')
            ->exists();
    }
}
