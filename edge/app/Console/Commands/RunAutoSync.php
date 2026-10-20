<?php

namespace App\Console\Commands;

use App\Services\Sync\SyncOrchestrator;
use Illuminate\Console\Command;

class RunAutoSync extends Command
{
    protected $signature = 'sync:auto';

    protected $description = 'Automatically push local changes and pull cloud deltas (retry with backoff when offline)';

    public function handle(SyncOrchestrator $orchestrator): int
    {
        $result = $orchestrator->run();

        if ($result['skipped'] ?? false) {
            $reason = $result['reason'] ?? 'unknown';
            $this->line("Sync skipped ({$reason}). Will retry automatically.");

            return self::SUCCESS;
        }

        if (isset($result['error'])) {
            $this->warn('Sync error: '.$result['error'].' — will retry automatically.');

            return self::SUCCESS;
        }

        $push = $result['push'] ?? [];
        $pull = $result['pull'] ?? [];
        $this->info(sprintf(
            'Auto sync OK — pushed %d events, pull mode %s, commands %d/%d ok.',
            $push['pushed'] ?? 0,
            $pull['mode'] ?? '—',
            $result['commands']['ok'] ?? 0,
            $result['commands']['pulled'] ?? 0,
        ));

        return self::SUCCESS;
    }
}
