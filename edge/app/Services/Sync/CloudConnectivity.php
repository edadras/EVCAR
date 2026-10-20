<?php

namespace App\Services\Sync;

use App\Models\StationIdentity;
use App\Models\SyncCursor;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

class CloudConnectivity
{
    public function isReachable(): bool
    {
        $station = StationIdentity::first();
        if (! $station?->cloud_api_url) {
            return false;
        }

        try {
            $url = rtrim($station->cloud_api_url, '/').'/up';

            return Http::timeout(3)->get($url)->successful();
        } catch (ConnectionException|\Throwable) {
            return false;
        }
    }

    public function canAttempt(string $stream = 'cloud_link'): bool
    {
        $cursor = SyncCursor::firstOrCreate(
            ['stream' => $stream],
            ['cursor_position' => 0]
        );

        return $cursor->next_retry_at === null || $cursor->next_retry_at <= now();
    }

    public function recordFailure(string $stream, string $error): void
    {
        $cursor = SyncCursor::firstOrCreate(
            ['stream' => $stream],
            ['cursor_position' => 0]
        );

        $failures = min(10, $cursor->consecutive_failures + 1);
        $delays = [1, 2, 4, 8, 15, 30];
        $minutes = $delays[min($failures - 1, count($delays) - 1)];

        $cursor->update([
            'consecutive_failures' => $failures,
            'last_error' => $error,
            'next_retry_at' => now()->addMinutes($minutes),
        ]);
    }

    public function recordSuccess(string $stream = 'cloud_link'): void
    {
        $cursor = SyncCursor::firstOrCreate(
            ['stream' => $stream],
            ['cursor_position' => 0]
        );

        $cursor->update([
            'consecutive_failures' => 0,
            'last_error' => null,
            'next_retry_at' => null,
        ]);
    }

    /** @return array{reachable:bool,can_attempt:bool,next_retry_at:?string,last_error:?string} */
    public function status(): array
    {
        $cursor = SyncCursor::where('stream', 'cloud_link')->first();

        return [
            'reachable' => $this->isReachable(),
            'can_attempt' => $this->canAttempt('cloud_link'),
            'next_retry_at' => $cursor?->next_retry_at?->toIso8601String(),
            'last_error' => $cursor?->last_error,
        ];
    }
}
