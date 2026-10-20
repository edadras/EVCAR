<?php

namespace App\Console\Commands;

use App\Models\AnprDetection;
use App\Services\CloudSyncClient;
use Illuminate\Console\Command;

class PushAnprToCloud extends Command
{
    protected $signature = 'sync:push-anpr {--limit=50 : Max detections per run}';

    protected $description = 'Push pending ANPR detections from Edge to Cloud';

    public function handle(CloudSyncClient $client): int
    {
        $limit = max(1, (int) $this->option('limit'));
        $pending = AnprDetection::query()
            ->where('pending_sync', true)
            ->orderBy('detected_at')
            ->limit($limit)
            ->get();

        if ($pending->isEmpty()) {
            $this->info('No pending ANPR detections.');

            return self::SUCCESS;
        }

        $pushed = 0;
        foreach ($pending as $detection) {
            if ($client->pushAnprDetection($detection)) {
                $pushed++;
            }
        }

        $this->info("Pushed {$pushed}/{$pending->count()} ANPR detections.");

        return self::SUCCESS;
    }
}
