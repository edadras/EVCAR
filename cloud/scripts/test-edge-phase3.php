<?php

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

use App\Models\ChargingSession;
use App\Models\Connector;
use App\Models\EdgeStation;
use App\Models\User;
use App\Services\App\ConnectivityService;
use App\Services\Sync\SyncIngestService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Str;

$edge = rtrim(getenv('EDGE_TEST_URL') ?: 'http://sc_edge_nginx', '/');
$fail = 0;
$results = [];

function ok(string $m): void
{
    global $results;
    $results[] = "OK   $m";
}
function bad(string $m): void
{
    global $results, $fail;
    $results[] = "FAIL $m";
    $fail++;
}

function httpJson(string $method, string $url, ?array $body = null, array $headers = []): array
{
    $ch = curl_init($url);
    $hdrs = array_merge(['Accept: application/json'], $headers);
    $opts = [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 60, CURLOPT_HTTPHEADER => $hdrs];
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

echo "=== Edge Phase 3 Tests ===\n";

$user = User::where('email', 'user1@demo.ir')->first();
if (! $user) {
    bad('demo user missing');
    exit(1);
}

$svc = app(ConnectivityService::class);
$token = $svc->edgeUserToken($user);
$authHeaders = [
    'X-SC-User-UUID: '.$user->uuid,
    'X-SC-Edge-Token: '.$token,
];

$health = httpJson('GET', "$edge/api/v1/health");
$health['code'] === 200 ? ok('edge health') : bad('edge health HTTP '.$health['code']);

$otpReq = httpJson('POST', "$edge/api/v1/auth/otp/request", ['phone' => $user->phone, 'purpose' => 'login']);
! empty($otpReq['body']['debug_code']) ? ok('OTP request (dev)') : bad('OTP request');

$code = $otpReq['body']['debug_code'] ?? '000000';
$otpVer = httpJson('POST', "$edge/api/v1/auth/otp/verify", ['phone' => $user->phone, 'code' => $code]);
! empty($otpVer['body']['edge_session_token']) ? ok('OTP verify') : bad('OTP verify');

$bearer = $otpVer['body']['edge_session_token'] ?? '';
$bearerHeaders = ['Authorization: Bearer '.$bearer];

$status = httpJson('GET', "$edge/api/v1/app/charging/status", null, $authHeaders);
$status['code'] === 200 ? ok('charging status (cloud token)') : bad('charging status HTTP '.$status['code']);

$connectorUuid = (string) Connector::query()
    ->whereHas('charger.station.edgeStation', fn ($q) => $q->where('edge_code', 'EDGE-AZADI-01'))
    ->value('uuid');

if ($connectorUuid === '') {
    bad('no cloud connector — run sync:pull-snapshot on edge');
} else {
    $auth = httpJson('POST', "$edge/api/v1/app/authorize", ['method' => 'qr', 'connector_uuid' => $connectorUuid], array_merge($authHeaders, ['Content-Type: application/json']));
    ($auth['code'] === 200 && ! empty($auth['body']['ok'])) ? ok('connector authorize (QR)') : bad('authorize HTTP '.$auth['code'].' '.$auth['raw']);

    $start = httpJson('POST', "$edge/api/v1/app/charging/start", ['connector_uuid' => $connectorUuid], array_merge($authHeaders, ['Content-Type: application/json']));
    ($start['code'] === 200 && ! empty($start['body']['ok'])) ? ok('charging start') : bad('charging start HTTP '.$start['code'].' '.$start['raw']);

    $stop = httpJson('POST', "$edge/api/v1/app/charging/stop", [], array_merge($authHeaders, ['Content-Type: application/json']));
    ($stop['code'] === 200 && ! empty($stop['body']['ok'])) ? ok('charging stop') : bad('charging stop HTTP '.$stop['code']);

    $sessionUuid = $stop['body']['session_uuid'] ?? '';
    $sessionUuid !== '' ? ok('stop returned session_uuid') : bad('stop missing session_uuid');

    $edgeStation = EdgeStation::where('edge_code', 'EDGE-AZADI-01')->first();
    if ($edgeStation && $sessionUuid) {
        $ingest = app(SyncIngestService::class);
        $ingest->ingest($edgeStation, [[
            'event_uuid' => (string) Str::uuid(),
            'event_type' => 'charging.session.started',
            'occurred_at' => now()->subMinutes(2)->toIso8601String(),
            'payload' => [
                'uuid' => $sessionUuid,
                'cloud_user_uuid' => $user->uuid,
                'cloud_connector_uuid' => $connectorUuid,
                'auth_method' => 'wallet',
                'status' => 'active',
                'started_at' => now()->subMinutes(2)->toIso8601String(),
            ],
        ], [
            'event_uuid' => (string) Str::uuid(),
            'event_type' => 'charging.session.stopped',
            'occurred_at' => now()->toIso8601String(),
            'payload' => array_merge($stop['body'], [
                'uuid' => $sessionUuid,
                'cloud_user_uuid' => $user->uuid,
                'status' => 'stopped',
                'stopped_at' => now()->toIso8601String(),
                'duration_seconds' => 5,
            ]),
        ]]);
        ChargingSession::where('edge_session_uuid', $sessionUuid)->where('status', 'stopped')->exists()
            ? ok('cloud sync ingest (session/wallet)')
            : bad('cloud sync ingest failed');
    }
}

$candidates = $svc->build($user)['candidates'];
count($cfg = $candidates) >= 2 ? ok('connectivity candidates ('.count($cfg).')') : bad('connectivity candidates');

foreach ($results as $r) {
    echo "$r\n";
}
echo $fail ? "\nFAILED ($fail)\n" : "\nALL PHASE 3 TESTS PASSED\n";
exit($fail ? 1 : 0);
