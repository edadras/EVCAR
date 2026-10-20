<?php

namespace App\Console\Commands;

use App\Services\Ocpp\CentralSystem;
use App\Services\Ocpp\ChargePointCommander;
use App\Services\Ocpp\WebSocket\WebSocketServer;
use App\Services\Station\StationConfigResolver;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class OcppServe extends Command
{
    protected $signature = 'ocpp:serve
        {--host= : Listen address (default OCPP_LISTEN_HOST)}
        {--port= : Listen port (default OCPP_LISTEN_PORT)}';

    protected $description = 'Run the built-in OCPP 1.6J central system (WebSocket server) for the station chargers';

    public function handle(CentralSystem $centralSystem, StationConfigResolver $config): int
    {
        $ocpp = $config->ocpp();
        if (($ocpp['driver'] ?? 'builtin') !== ChargePointCommander::DRIVER_BUILTIN) {
            $this->warn('OCPP_DRIVER is "'.($ocpp['driver'] ?? '').'" — the built-in server is not used. Set OCPP_DRIVER=builtin.');

            return self::FAILURE;
        }

        $host = (string) ($this->option('host') ?: ($ocpp['listen_host'] ?? '0.0.0.0'));
        $port = (int) ($this->option('port') ?: ($ocpp['listen_port'] ?? 9000));

        DB::connection()->disableQueryLog();
        ChargePointCommander::markInsideServer();
        $centralSystem->boot();

        $server = new WebSocketServer($host, $port, $centralSystem, Log::channel());
        $server->listen();

        if (function_exists('pcntl_signal')) {
            pcntl_signal(SIGTERM, fn () => $server->stop());
            pcntl_signal(SIGINT, fn () => $server->stop());
        }

        $this->info("OCPP 1.6J central system listening on ws://{$host}:{$server->port()}/ocpp/{ChargePointId}");
        $server->run();
        $this->info('OCPP server stopped.');

        return self::SUCCESS;
    }
}
