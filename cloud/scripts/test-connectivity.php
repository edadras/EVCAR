<?php

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

use App\Models\User;
use App\Services\App\ConnectivityService;
use Illuminate\Contracts\Console\Kernel;

$base = getenv('APP_TEST_URL') ?: 'http://sc_cloud_nginx';
$edge = getenv('EDGE_TEST_URL') ?: 'http://sc_edge_nginx';
$fail = 0;

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

$results = [];
echo "=== Connectivity Tests ===\n";

$ch = curl_init("$edge/api/v1/health");
curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 10]);
$body = curl_exec($ch);
$code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);
$code === 200 && str_contains($body, 'safircharge-edge') ? ok('edge health') : bad("edge health HTTP $code");

$ch = curl_init("$base/up");
curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 10]);
curl_exec($ch);
$code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);
$code === 200 ? ok('cloud up') : bad("cloud up HTTP $code");

$user = User::where('email', 'user1@demo.ir')->first();
$svc = app(ConnectivityService::class);
$cfg = $svc->build($user);
count($cfg['candidates']) >= 2 ? ok('connectivity candidates') : bad('connectivity candidates');

$token = $svc->edgeUserToken($user);
$ch = curl_init("$edge/api/v1/app/charging/status");
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 10,
    CURLOPT_HTTPHEADER => [
        'Accept: application/json',
        'X-SC-User-UUID: '.$user->uuid,
        'X-SC-Edge-Token: '.$token,
    ],
]);
$body = curl_exec($ch);
$code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);
$code === 200 && str_contains($body, '"source":"edge"') ? ok('edge charging status API') : bad("edge charging status HTTP $code body=$body");

foreach ($results as $r) {
    echo "$r\n";
}
echo $fail ? "\nFAILED ($fail)\n" : "\nALL CONNECTIVITY TESTS PASSED\n";
exit($fail ? 1 : 0);
