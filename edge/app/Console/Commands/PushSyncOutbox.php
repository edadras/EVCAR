<?php

namespace App\Console\Commands;

use App\Services\Sync\SyncPushRunner;
use Illuminate\Console\Command;

class PushSyncOutbox extends Command
{
    protected $signature = 'sync:push-outbox {--limit=50}';

    protected $description = 'Push pending sync_outbox events to cloud';

    public function handle(SyncPushRunner $runner): int
    {
        $result = $runner->pushPending(max(1, (int) $this->option('limit')));

        if ($result['pushed'] === 0 && $result['failed'] === 0) {
            $this->info('No pending outbox events.');

            return self::SUCCESS;
        }

        $this->info(sprintf(
            'Pushed %d events (%d failed, cloud accepted %d).',
            $result['pushed'],
            $result['failed'],
            $result['accepted']
        ));

        return self::SUCCESS;
    }
}
