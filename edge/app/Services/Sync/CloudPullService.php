<?php

namespace App\Services\Sync;

use App\Models\StationIdentity;
use App\Models\SyncCursor;
use App\Services\Station\StationConfigResolver;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;

class CloudPullService
{
    public function __construct(
        private SnapshotApplier $applier,
        private StationConfigResolver $config,
    ) {}

    /** @return array{mode:string,stats:array<string,int>,cursor:?string} */
    public function pull(): array
    {
        $station = StationIdentity::first();
        if (! $station) {
            throw new \RuntimeException('station_identity not configured');
        }

        $base = $this->config->cloudApiUrl();
        $token = $this->config->cloudSyncToken();
        if (! $base || ! $token) {
            throw new \RuntimeException('آدرس یا توکن سینک ابر تنظیم نشده است (CLOUD_API_URL / CLOUD_SYNC_TOKEN).');
        }

        $cursor = SyncCursor::firstOrCreate(
            ['stream' => 'cloud_pull'],
            ['cursor_position' => 0]
        );

        $useSnapshot = $cursor->last_synced_at === null;
        $url = $useSnapshot
            ? "{$base}/api/v1/sync/snapshot"
            : "{$base}/api/v1/sync/delta?since=".urlencode($cursor->last_synced_at->toIso8601String());

        $response = Http::withToken($token)->timeout(60)->get($url);

        if (! $response->successful()) {
            throw new \RuntimeException('Cloud sync failed: HTTP '.$response->status());
        }

        $data = $response->json('data');
        if (! is_array($data)) {
            throw new \RuntimeException('Empty sync payload');
        }

        $stats = $this->applier->apply($data);

        $newCursor = $data['cursor'] ?? now()->toIso8601String();
        $cursor->update([
            'last_synced_at' => Carbon::parse($newCursor),
            'cursor_position' => $cursor->cursor_position + 1,
        ]);

        return [
            'mode' => $useSnapshot ? 'snapshot' : ($data['mode'] ?? 'delta'),
            'stats' => $stats,
            'cursor' => $newCursor,
        ];
    }
}
