<?php

namespace App\Console\Commands;

use App\Services\Sync\CloudCommandPullService;
use Illuminate\Console\Command;

class PullCloudCommands extends Command
{
    protected $signature = 'sync:pull-commands';

    protected $description = 'Pull pending remote commands from cloud and execute locally via OCPP Gateway';

    public function handle(CloudCommandPullService $pull): int
    {
        try {
            $stats = $pull->pullAndExecute();
        } catch (\Throwable $e) {
            $this->warn($e->getMessage());

            return self::SUCCESS;
        }

        $this->info(sprintf(
            'Commands: %d pulled, %d ok, %d failed',
            $stats['pulled'],
            $stats['ok'],
            $stats['failed']
        ));

        return self::SUCCESS;
    }
}
