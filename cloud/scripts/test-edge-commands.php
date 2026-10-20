<?php

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Connector;
use App\Models\EdgeStation;
use App\Models\Station;
use App\Models\User;
use App\Services\Edge\EdgeCommandService;

$fail = 0;
$results = [];

function ok(string $m): void { global $results; $results[] = "OK   $m"; }
function bad(string $m): void { global $results, $fail; $results[] = "FAIL $m"; $fail++; }

function httpJson(string $method, string $url, ?array $body = null, array $headers = []): array
{
    $ch = curl_init($url);
    $hdrs = array_merge(['Accept: application/json'], $headers);
    $opts = [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 20, CURLOPT_HTTPHEADER => $hdrs];
    if ($method === 'POST') {
        $opts[CURLOPT_POST] = true;
        $opts[CURLOPT_POSTFIELDS] = json_encode($body ?? []);
        $hdrs[] = 'Content-Type: application/json';
        $opts[CURLOPT_HTTPHEADER] = $hdrs;
    }
    curl_setopt_array($ch, $opts);
    $raw = curl_exec($ch) ?: '';
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    return ['code' => $code, 'body' => json_decode($raw, true) ?? [], 'raw' => $raw];
}

echo "=== Edge Commands (Cloud→Edge→OCPP) ===\n";

$station = Station::query()->whereHas('edgeStation')->with('edgeStation')->first();
if (! $station) {
    bad('no station with edge');
    exit(1);
}
ok('station: '.$station->name);

$connector = Connector::query()
    ->whereHas('charger', fn ($q) => $q->where('station_id', $station->id))
    ->with('charger')
    ->first();

if (! $connector) {
    bad('no connector');
    exit(1);
}
ok('connector #'.$connector->connector_number);

$admin = User::where('email', 'admin@demo.ir')->first();
$svc = app(EdgeCommandService::class);
$cmd = $svc->queue($station, 'unlock', $connector, [], $admin);
$cmd->status === 'pending' ? ok('queued command '.$cmd->uuid) : bad('queue failed');

$token = getenv('EDGE_CLOUD_SYNC_TOKEN') ?: 'dev-sync-token';
$pull = httpJson('GET', 'http://sc_cloud_nginx/api/v1/sync/commands', null, [
    'Authorization: Bearer '.$token,
]);
($pull['code'] === 200 && count($pull['body']['data'] ?? []) >= 1)
    ? ok('edge pull commands')
    : bad('edge pull HTTP '.$pull['code'].' '.$pull['raw']);

$uuid = $pull['body']['data'][0]['uuid'] ?? $cmd->uuid;
$ack = httpJson('POST', 'http://sc_cloud_nginx/api/v1/sync/commands/'.$uuid.'/ack', [
    'ok' => true,
    'result' => ['simulated' => true],
], ['Authorization: Bearer '.$token, 'Content-Type: application/json']);
($ack['code'] === 200 && ($ack['body']['status'] ?? '') === 'completed')
    ? ok('ack command')
    : bad('ack HTTP '.$ack['code'].' '.$ack['raw']);

$cmdEdge = $svc->queue($station, 'reset', $connector, ['type' => 'Soft'], $admin);
$cmdEdge->status === 'pending' ? ok('queued for edge run '.$cmdEdge->uuid) : bad('edge queue failed');

foreach ($results as $r) {
    echo $r."\n";
}
echo $fail ? "\nFAILED ($fail)\n" : "\nALL PASSED\n";
exit($fail ? 1 : 0);
