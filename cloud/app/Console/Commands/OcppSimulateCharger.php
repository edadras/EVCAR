<?php

namespace App\Console\Commands;

use App\Services\Ocpp\WebSocket\WebSocketClient;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * شبیه‌ساز شارژر OCPP 1.6J — برای تست کامل ایستگاه بدون سخت‌افزار.
 * دقیقاً مثل یک شارژر واقعی از طریق WebSocket به ocpp:serve وصل می‌شود.
 */
class OcppSimulateCharger extends Command
{
    protected $signature = 'ocpp:simulate-charger
        {charge_point_id : Charge point identity (must exist in cloud)}
        {--url= : Central system URL (default ws://127.0.0.1:<OCPP_LISTEN_PORT>/ocpp)}
        {--connectors=1 : Number of connectors}
        {--power=22 : Simulated charging power in kW}
        {--meter-interval=10 : Seconds between MeterValues}
        {--rfid= : Swipe this RFID idTag on connector 1 right after boot}
        {--password= : Basic auth password}
        {--max-seconds=0 : Exit after N seconds (0 = run forever)}';

    protected $description = 'Simulate an OCPP 1.6J charge point connected to the built-in central system';

    private WebSocketClient $ws;

    /** @var array<string, array{action:string, payload:array<string,mixed>}> */
    private array $pending = [];

    /** @var array<int, array{status:string, tx:?int, idTag:?string, meter:float, startedAt:?float, lastMeterAt?:float}> */
    private array $connectors = [];

    private float $power = 22;

    public function handle(): int
    {
        $cpId = (string) $this->argument('charge_point_id');
        $base = rtrim((string) ($this->option('url') ?: 'ws://127.0.0.1:'.config('safircharge.ocpp.listen_port', 9100).'/ocpp'), '/');
        $this->power = max(1, (float) $this->option('power'));
        $meterInterval = max(1, (int) $this->option('meter-interval'));
        $maxSeconds = (int) $this->option('max-seconds');

        $headers = ['Sec-WebSocket-Protocol' => 'ocpp1.6'];
        if ($password = $this->option('password')) {
            $headers['Authorization'] = 'Basic '.base64_encode($cpId.':'.$password);
        }

        $this->ws = WebSocketClient::connect($base.'/'.rawurlencode($cpId), $headers);
        $this->info("Connected as {$cpId}");

        for ($i = 1; $i <= max(1, (int) $this->option('connectors')); $i++) {
            $this->connectors[$i] = ['status' => 'Available', 'tx' => null, 'idTag' => null, 'meter' => 1_000_000 + $i * 100_000, 'startedAt' => null];
        }

        $boot = $this->call_('BootNotification', [
            'chargePointVendor' => 'SafirSim',
            'chargePointModel' => 'SIM-'.(int) $this->power.'kW',
            'chargePointSerialNumber' => 'SIM-'.$cpId,
            'firmwareVersion' => '1.0.0',
        ]);

        if (($boot['status'] ?? null) !== 'Accepted') {
            $this->error('BootNotification not accepted: '.json_encode($boot));
            $this->ws->close();

            return self::FAILURE;
        }

        $heartbeat = max(10, (int) ($boot['interval'] ?? 60));
        foreach ($this->connectors as $n => $c) {
            $this->status($n, $c['status']);
        }

        if ($rfid = $this->option('rfid')) {
            $auth = $this->call_('Authorize', ['idTag' => $rfid]);
            if (($auth['idTagInfo']['status'] ?? null) === 'Accepted') {
                $this->beginTransaction(1, (string) $rfid);
            } else {
                $this->warn('RFID rejected: '.json_encode($auth));
            }
        }

        $started = microtime(true);
        $lastHeartbeat = $lastMeter = microtime(true);

        while ($maxSeconds <= 0 || microtime(true) - $started < $maxSeconds) {
            $message = $this->ws->receive(0.3);
            if ($message !== null) {
                $this->onMessage($message);
            }

            if ($this->deferred) {
                $fn = $this->deferred;
                $this->deferred = null;
                $fn();
            }

            $now = microtime(true);
            if ($now - $lastHeartbeat >= $heartbeat) {
                $lastHeartbeat = $now;
                $this->call_('Heartbeat', []);
            }
            if ($now - $lastMeter >= $meterInterval) {
                $lastMeter = $now;
                $this->sendMeterValues();
            }
        }

        $this->ws->close();

        return self::SUCCESS;
    }

    /** @return array<string, mixed> */
    private function onMessage(string $raw): array
    {
        $frame = json_decode($raw, true);
        if (! is_array($frame)) {
            return [];
        }

        if ($frame[0] === 3 || $frame[0] === 4) {
            $pending = $this->pending[$frame[1]] ?? null;
            unset($this->pending[$frame[1]]);
            $this->line('<- '.($pending['action'] ?? '?').' '.json_encode($frame[2] ?? null, JSON_UNESCAPED_UNICODE));
            if ($frame[0] === 3 && ($pending['action'] ?? '') === 'StartTransaction') {
                $n = (int) $pending['payload']['connectorId'];
                $this->connectors[$n]['tx'] = (int) ($frame[2]['transactionId'] ?? 0);
                $status = $frame[2]['idTagInfo']['status'] ?? 'Invalid';
                if ($status !== 'Accepted') {
                    $this->deferred = fn () => $this->endTransaction($n, 'DeAuthorized');
                }
            }

            return $frame[0] === 3 && is_array($frame[2] ?? null) ? $frame[2] : [];
        }

        if ($frame[0] !== 2) {
            return [];
        }

        [, $id, $action, $payload] = $frame + [null, null, '', []];
        $this->line("<= {$action} ".json_encode($payload, JSON_UNESCAPED_UNICODE));

        $response = match ($action) {
            'RemoteStartTransaction' => $this->remoteStart($payload),
            'RemoteStopTransaction' => $this->remoteStop($payload),
            'GetConfiguration' => ['configurationKey' => [
                ['key' => 'HeartbeatInterval', 'readonly' => false, 'value' => '60'],
                ['key' => 'MeterValueSampleInterval', 'readonly' => false, 'value' => (string) $this->option('meter-interval')],
            ]],
            'TriggerMessage', 'Reset', 'UnlockConnector', 'ChangeAvailability', 'SetChargingProfile',
            'ChangeConfiguration', 'ClearCache', 'ClearChargingProfile' => ['status' => $action === 'UnlockConnector' ? 'Unlocked' : 'Accepted'],
            'GetDiagnostics' => ['fileName' => 'diag.log'],
            default => [],
        };

        $this->ws->send(json_encode([3, $id, $response === [] ? (object) [] : $response]));

        return [];
    }

    /** @return array<string, string> */
    private function remoteStart(array $payload): array
    {
        $n = (int) ($payload['connectorId'] ?? 1);
        if (! isset($this->connectors[$n]) || $this->connectors[$n]['tx'] !== null) {
            return ['status' => 'Rejected'];
        }

        // پاسخ را ابتدا می‌فرستیم؛ شروع تراکنش بلافاصله پس از آن (کابل از قبل وصل فرض می‌شود).
        $idTag = (string) ($payload['idTag'] ?? '');
        $this->deferred = fn () => $this->beginTransaction($n, $idTag);

        return ['status' => 'Accepted'];
    }

    /** @var (\Closure(): void)|null */
    private ?\Closure $deferred = null;

    /** @return array<string, string> */
    private function remoteStop(array $payload): array
    {
        foreach ($this->connectors as $n => $c) {
            if ($c['tx'] !== null && $c['tx'] === (int) ($payload['transactionId'] ?? -1)) {
                $this->deferred = fn () => $this->endTransaction($n, 'Remote');

                return ['status' => 'Accepted'];
            }
        }

        return ['status' => 'Rejected'];
    }

    private function beginTransaction(int $n, string $idTag): void
    {
        $this->status($n, 'Preparing');
        $this->connectors[$n]['idTag'] = $idTag;
        $this->connectors[$n]['startedAt'] = microtime(true);
        $this->connectors[$n]['lastMeterAt'] = microtime(true);
        $this->connectors[$n]['tx'] = 0;
        $this->call_('StartTransaction', [
            'connectorId' => $n,
            'idTag' => $idTag,
            'meterStart' => (int) $this->connectors[$n]['meter'],
            'timestamp' => gmdate('Y-m-d\TH:i:s\Z'),
        ]);
        $this->status($n, 'Charging');
    }

    private function endTransaction(int $n, string $reason): void
    {
        $tx = $this->connectors[$n]['tx'];
        $this->advanceMeter($n);
        if ($tx) {
            $this->call_('StopTransaction', [
                'transactionId' => $tx,
                'idTag' => $this->connectors[$n]['idTag'],
                'meterStop' => (int) $this->connectors[$n]['meter'],
                'timestamp' => gmdate('Y-m-d\TH:i:s\Z'),
                'reason' => $reason,
            ]);
        }
        $this->connectors[$n]['tx'] = null;
        $this->connectors[$n]['startedAt'] = null;
        $this->status($n, 'Finishing');
        $this->status($n, 'Available');
    }

    private function sendMeterValues(): void
    {
        foreach ($this->connectors as $n => $c) {
            if (! $c['tx']) {
                continue;
            }
            $this->advanceMeter($n);
            $this->call_('MeterValues', [
                'connectorId' => $n,
                'transactionId' => $c['tx'],
                'meterValue' => [[
                    'timestamp' => gmdate('Y-m-d\TH:i:s\Z'),
                    'sampledValue' => [
                        ['value' => (string) (int) $this->connectors[$n]['meter'], 'measurand' => 'Energy.Active.Import.Register', 'unit' => 'Wh', 'context' => 'Sample.Periodic'],
                        ['value' => (string) round($this->power * 1000), 'measurand' => 'Power.Active.Import', 'unit' => 'W'],
                        ['value' => '400', 'measurand' => 'Voltage', 'unit' => 'V'],
                        ['value' => (string) round($this->power * 1000 / 400 / 1.732, 1), 'measurand' => 'Current.Import', 'unit' => 'A'],
                    ],
                ]],
            ]);
        }
    }

    private function advanceMeter(int $n): void
    {
        $now = microtime(true);
        $last = $this->connectors[$n]['lastMeterAt'] ?? $this->connectors[$n]['startedAt'] ?? $now;
        if ($this->connectors[$n]['tx'] !== null) {
            $this->connectors[$n]['meter'] += $this->power * 1000 * (($now - $last) / 3600);
        }
        $this->connectors[$n]['lastMeterAt'] = $now;
    }

    private function status(int $n, string $status): void
    {
        $this->connectors[$n]['status'] = $status;
        $this->call_('StatusNotification', [
            'connectorId' => $n,
            'errorCode' => 'NoError',
            'status' => $status,
            'timestamp' => gmdate('Y-m-d\TH:i:s\Z'),
        ]);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function call_(string $action, array $payload): array
    {
        $id = (string) Str::uuid();
        $this->pending[$id] = ['action' => $action, 'payload' => $payload];
        $this->ws->send(json_encode([2, $id, $action, $payload === [] ? (object) [] : $payload]));
        $this->line("-> {$action}");

        // مانند شارژر واقعی، منتظر پاسخ هر CALL می‌مانیم (یک CALL باز در هر جهت).
        $deadline = microtime(true) + 15;
        while (isset($this->pending[$id]) && microtime(true) < $deadline) {
            $message = $this->ws->receive(0.5);
            if ($message !== null) {
                $frame = json_decode($message, true);
                $result = $this->onMessage($message);
                if (is_array($frame) && ($frame[1] ?? null) === $id) {
                    return $result;
                }
            }
        }

        return [];
    }
}
