<?php

namespace App\Services\Sync;

use App\Models\EdgeStation;
use App\Models\SyncInbox;
use App\Services\Sync\Handlers\AnprDetectedHandler;
use App\Services\Sync\Handlers\ChargingSessionStartedHandler;
use App\Services\Sync\Handlers\ChargingSessionStoppedHandler;
use App\Services\Sync\Handlers\WalletDebitedHandler;
use Illuminate\Support\Facades\DB;

class SyncIngestService
{
    public function __construct(
        private AnprDetectedHandler $anpr,
        private ChargingSessionStartedHandler $sessionStarted,
        private ChargingSessionStoppedHandler $sessionStopped,
        private WalletDebitedHandler $walletDebited,
    ) {}

    /**
     * @param  array<int, array<string, mixed>>  $events
     * @return array{accepted: int, duplicates: int, errors: array<int, array<string, string>>}
     */
    public function ingest(EdgeStation $edge, array $events): array
    {
        $accepted = 0;
        $duplicates = 0;
        $errors = [];

        foreach ($events as $index => $event) {
            try {
                $result = $this->ingestOne($edge, $event);
                if ($result === 'duplicate') {
                    $duplicates++;
                } else {
                    $accepted++;
                }
            } catch (\Throwable $e) {
                $errors[] = [
                    'index' => (string) $index,
                    'event_uuid' => (string) ($event['event_uuid'] ?? ''),
                    'message' => $e->getMessage(),
                ];
            }
        }

        $edge->update([
            'last_sync_at' => now(),
            'last_heartbeat_at' => now(),
            'sync_status' => 'online',
        ]);

        return compact('accepted', 'duplicates', 'errors');
    }

    /** @param  array<string, mixed>  $event */
    private function ingestOne(EdgeStation $edge, array $event): string
    {
        $eventUuid = (string) ($event['event_uuid'] ?? '');
        if ($eventUuid === '') {
            throw new \InvalidArgumentException('event_uuid الزامی است.');
        }

        $eventType = (string) ($event['event_type'] ?? '');
        $payload = (array) ($event['payload'] ?? []);
        $occurredAt = $event['occurred_at'] ?? now();

        if (SyncInbox::where('event_uuid', $eventUuid)->exists()) {
            return 'duplicate';
        }

        return DB::transaction(function () use ($edge, $eventUuid, $eventType, $payload, $occurredAt): string {
            $inbox = SyncInbox::create([
                'event_uuid' => $eventUuid,
                'edge_station_uuid' => $edge->uuid,
                'event_type' => $eventType,
                'aggregate_type' => $this->aggregateType($eventType),
                'aggregate_uuid' => $payload['detection_uuid'] ?? $payload['uuid'] ?? null,
                'payload' => $payload,
                'occurred_at' => $occurredAt,
                'received_at' => now(),
                'status' => 'processing',
            ]);

            try {
                match ($eventType) {
                    'anpr.detected' => $this->anpr->handle($edge, $payload),
                    'charging.session.started' => $this->sessionStarted->handle($edge, $payload),
                    'charging.session.stopped' => $this->sessionStopped->handle($edge, $payload),
                    'wallet.debited' => $this->walletDebited->handle($edge, $payload),
                    'station.heartbeat' => null,
                    default => throw new \InvalidArgumentException("نوع رویداد پشتیبانی نمی‌شود: {$eventType}"),
                };

                $inbox->update([
                    'status' => 'processed',
                    'processed_at' => now(),
                ]);
            } catch (\Throwable $e) {
                $inbox->update([
                    'status' => 'failed',
                    'attempts' => $inbox->attempts + 1,
                    'last_error' => $e->getMessage(),
                ]);

                throw $e;
            }

            return 'accepted';
        });
    }

    private function aggregateType(string $eventType): ?string
    {
        return match ($eventType) {
            'anpr.detected' => 'anpr_detection',
            'charging.session.started', 'charging.session.stopped' => 'charging_session',
            'wallet.debited' => 'wallet_transaction',
            'station.heartbeat' => 'edge_station',
            default => null,
        };
    }
}
