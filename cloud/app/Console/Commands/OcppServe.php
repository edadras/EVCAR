<?php

namespace App\Console\Commands;

use App\Services\Ocpp\CloudCentralSystem;
use App\Services\Ocpp\CloudOcppCommander;
use App\Services\Ocpp\WebSocket\WebSocketServer;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class OcppServe extends Command
{
    protected $signature = 'ocpp:serve
        {--host= : Listen address (default OCPP_LISTEN_HOST)}
        {--port= : Listen port (default OCPP_LISTEN_PORT)}';

    protected $description = 'Run the cloud OCPP 1.6J server for chargers of stations without an edge controller';

    public function handle(CloudCentralSystem $centralSystem): int
    {
        $host = (string) ($this->option('host') ?: config('safircharge.ocpp.listen_host', '0.0.0.0'));
        $port = (int) ($this->option('port') ?: config('safircharge.ocpp.listen_port', 9100));

        DB::connection()->disableQueryLog();
        CloudOcppCommander::markInsideServer();
        $centralSystem->boot();

        $server = new WebSocketServer($host, $port, $centralSystem, Log::channel());
        $server->listen();

        if (function_exists('pcntl_signal')) {
            pcntl_signal(SIGTERM, fn () => $server->stop());
            pcntl_signal(SIGINT, fn () => $server->stop());
        }

        $this->info("Cloud OCPP 1.6J server listening on ws://{$host}:{$server->port()}/ocpp/{ChargePointId}");
        $server->run();

        return self::SUCCESS;
    }
}
