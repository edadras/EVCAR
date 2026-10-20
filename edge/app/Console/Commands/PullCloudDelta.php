<?php

namespace App\Console\Commands;

use App\Services\Sync\CloudPullService;
use Illuminate\Console\Command;

class PullCloudDelta extends Command
{
    protected $signature = 'sync:pull-delta';

    protected $description = 'Pull cloud snapshot (first run) or delta updates into local cache';

    public function handle(CloudPullService $pull): int
    {
        try {
            $result = $pull->pull();
        } catch (\Throwable $e) {
            $this->warn($e->getMessage());

            return self::SUCCESS;
        }

        $s = $result['stats'];
        $this->info(sprintf(
            'Sync %s applied: %d users, %d vehicles, %d rfid, %d qr, %d chargers, %d tariffs',
            $result['mode'],
            $s['users'],
            $s['vehicles'],
            $s['rfid'],
            $s['qr'],
            $s['chargers'],
            $s['tariffs']
        ));

        return self::SUCCESS;
    }
}
