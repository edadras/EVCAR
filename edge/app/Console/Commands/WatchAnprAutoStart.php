<?php

namespace App\Console\Commands;

use App\Services\Anpr\AnprAutoStartService;
use Illuminate\Console\Command;

class WatchAnprAutoStart extends Command
{
    protected $signature = 'anpr:watch-auto-start';

    protected $description = 'Start charging when ANPR-detected user plugs into mapped connector';

    public function handle(AnprAutoStartService $autoStart): int
    {
        $started = $autoStart->watchConnectors();

        if ($started > 0) {
            $this->info("Auto-started {$started} charging session(s) from ANPR.");
        }

        return self::SUCCESS;
    }
}
