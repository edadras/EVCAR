<?php

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Connector;
use App\Models\RfidCard;
use App\Models\Station;
use App\Models\User;
use App\Models\Vehicle;

$base = getenv('APP_TEST_URL') ?: 'http://sc_cloud_nginx';
$cookieFile = sys_get_temp_dir().'/sc_app_actions_cookies.txt';
@unlink($cookieFile);

function req(string $method, string $url, array $opts = []): array
{
    global $cookieFile;
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HEADER => true,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_COOKIEJAR => $cookieFile,
        CURLOPT_COOKIEFILE => $cookieFile,
        CURLOPT_TIMEOUT => 30,
    ]);
    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $opts['body'] ?? '');
    }
    if ($method === 'PUT' || $method === 'DELETE') {
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $opts['body'] ?? '');
    }
    if (! empty($opts['headers'])) {
        curl_setopt($ch, CURLOPT_HTTPHEADER, $opts['headers']);
    }
    $raw = curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    $headerSize = strpos($raw, "\r\n\r\n");
    $body = $headerSize !== false ? substr($raw, $headerSize + 4) : $raw;

    return ['code' => $code, 'body' => $body];
}

function csrf(string $html): ?string
{
    if (preg_match('/name="csrf-token"\s+content="([^"]+)"/', $html, $m)) {
        return $m[1];
    }
    if (preg_match('/name="_token"\s+value="([^"]+)"/', $html, $m)) {
        return $m[1];
    }

    return null;
}

function login(): ?string
{
    global $base;
    $r = req('GET', "$base/app/login");
    $token = csrf($r['body']);
    if (! $token) {
        return null;
    }
    req('POST', "$base/app/login", [
        'body' => http_build_query(['_token' => $token, 'phone' => 'user3@demo.ir', 'password' => 'Demo@123456']),
        'headers' => ['Content-Type: application/x-www-form-urlencoded'],
    ]);

    return $token;
}

$fail = 0;
$results = [];

function ok(string $msg): void
{
    global $results;
    $results[] = "OK   $msg";
}

function bad(string $msg, int $code = 0): void
{
    global $results, $fail;
    $results[] = "FAIL $msg".($code ? " HTTP $code" : '');
    $fail++;
}

echo "=== Customer App Action Tests ===\n";

$token = login();
if (! $token) {
    echo "Login failed\n";
    exit(1);
}
ok('login user3@demo.ir');

// Ensure no active session blocks charging test
$user = User::where('email', 'user3@demo.ir')->first();
$user?->chargingSessions()->where('status', 'active')->update(['status' => 'stopped', 'stopped_at' => now()]);

$station = Station::where('status', 'active')->whereNotNull('code')->first();
if ($station) {
    $r = req('GET', "$base/app/charger?station=".urlencode($station->code));
    $r['code'] === 200 ? ok("charger {$station->code}") : bad("charger {$station->code}", $r['code']);
} else {
    bad('no active station in DB');
}

// Vehicle store / update / delete
$r = req('GET', "$base/app/vehicle");
$token = csrf($r['body']) ?: $token;
str_contains($r['body'], 'gv-edit') ? ok('vehicle page edit UI') : bad('vehicle page edit UI');
$r = req('POST', "$base/app/vehicle", [
    'body' => http_build_query([
        '_token' => $token,
        'brand_model' => 'تست خودرو E2E',
        'plate_two' => '99',
        'plate_letter' => 'ب',
        'plate_three' => '888',
        'plate_province' => '22',
        'is_default' => '0',
    ]),
    'headers' => ['Content-Type: application/x-www-form-urlencoded'],
]);
in_array($r['code'], [302, 200], true) ? ok('POST vehicle') : bad('POST vehicle', $r['code']);

$testVehicle = Vehicle::where('user_id', User::where('email', 'user3@demo.ir')->value('id'))
    ->where('brand', 'تست')
    ->latest('id')
    ->first();
if ($testVehicle) {
    $r = req('GET', "$base/app/vehicle");
    $token = csrf($r['body']) ?: $token;
    $r = req('PUT', "$base/app/vehicle/{$testVehicle->id}", [
        'body' => http_build_query([
            '_token' => $token,
            '_method' => 'PUT',
            'brand_model' => 'تست خودرو E2E ویرایش',
            'plate_two' => '88',
            'plate_letter' => 'ج',
            'plate_three' => '777',
            'plate_province' => '33',
            'battery_kwh' => '55',
            'is_default' => '0',
        ]),
        'headers' => ['Content-Type: application/x-www-form-urlencoded'],
    ]);
    in_array($r['code'], [302, 200], true) ? ok('PUT vehicle') : bad('PUT vehicle', $r['code']);

    $r = req('GET', "$base/app/vehicle");
    $token = csrf($r['body']) ?: $token;
    $r = req('DELETE', "$base/app/vehicle/{$testVehicle->id}", [
        'body' => http_build_query(['_token' => $token, '_method' => 'DELETE']),
        'headers' => ['Content-Type: application/x-www-form-urlencoded'],
    ]);
    in_array($r['code'], [302, 200], true) ? ok('DELETE vehicle') : bad('DELETE vehicle', $r['code']);
} else {
    bad('vehicle test record not found');
}

// RFID store / update / delete
$r = req('GET', "$base/app/rfid");
$token = csrf($r['body']) ?: $token;
str_contains($r['body'], 'کارت‌های من') ? ok('rfid page card list') : bad('rfid page card list');
$testTag = 'CN-TEST-'.random_int(1000, 9999);
$r = req('POST', "$base/app/rfid", [
    'body' => http_build_query([
        '_token' => $token,
        'id_tag' => $testTag,
        'card_label' => 'کارت تست',
    ]),
    'headers' => ['Content-Type: application/x-www-form-urlencoded'],
]);
in_array($r['code'], [302, 200], true) ? ok('POST rfid') : bad('POST rfid', $r['code']);

$testCard = RfidCard::where('user_id', User::where('email', 'user3@demo.ir')->value('id'))
    ->where('id_tag', $testTag)
    ->first();
if ($testCard) {
    $r = req('GET', "$base/app/rfid");
    $token = csrf($r['body']) ?: $token;
    $r = req('PUT', "$base/app/rfid/{$testCard->id}", [
        'body' => http_build_query([
            '_token' => $token,
            '_method' => 'PUT',
            'card_label' => 'کارت تست ویرایش',
        ]),
        'headers' => ['Content-Type: application/x-www-form-urlencoded'],
    ]);
    in_array($r['code'], [302, 200], true) ? ok('PUT rfid') : bad('PUT rfid', $r['code']);

    $r = req('GET', "$base/app/rfid");
    $token = csrf($r['body']) ?: $token;
    $r = req('DELETE', "$base/app/rfid/{$testCard->id}", [
        'body' => http_build_query(['_token' => $token, '_method' => 'DELETE']),
        'headers' => ['Content-Type: application/x-www-form-urlencoded'],
    ]);
    in_array($r['code'], [302, 200], true) ? ok('DELETE rfid') : bad('DELETE rfid', $r['code']);
} else {
    bad('rfid test record not found');
}

// Plate update
$r = req('GET', "$base/app/plate");
$token = csrf($r['body']) ?: $token;
$r = req('POST', "$base/app/plate", [
    'body' => http_build_query([
        '_token' => $token,
        'plate_two' => '77',
        'plate_letter' => 'ق',
        'plate_three' => '123',
        'plate_province' => '21',
    ]),
    'headers' => ['Content-Type: application/x-www-form-urlencoded'],
]);
in_array($r['code'], [302, 200], true) ? ok('POST plate') : bad('POST plate', $r['code']);

// Reservation
$connector = Connector::whereHas('charger.station', fn ($q) => $q->where('status', 'active'))->first();
if ($connector && $station) {
    $r = req('GET', "$base/app/reserve");
    $token = csrf($r['body']) ?: $token;
    $from = now()->addHours(2)->format('Y-m-d H:i:s');
    $until = now()->addHours(2)->addMinutes(30)->format('Y-m-d H:i:s');
    $r = req('POST', "$base/app/reserve", [
        'body' => http_build_query([
            '_token' => $token,
            'station_id' => $station->id,
            'connector_id' => $connector->id,
            'reserved_from' => $from,
            'reserved_until' => $until,
        ]),
        'headers' => ['Content-Type: application/x-www-form-urlencoded'],
    ]);
    in_array($r['code'], [302, 200], true) ? ok('POST reserve') : bad('POST reserve', $r['code']);
} else {
    bad('reserve skipped - no connector');
}

// Charging start (if no active session)
$hasActive = $user?->chargingSessions()->where('status', 'active')->exists();
if (! $hasActive && $connector) {
    $r = req('GET', "$base/app/charger?station=".urlencode($station->code));
    $token = csrf($r['body']) ?: $token;
    $r = req('POST', "$base/app/charging/start", [
        'body' => http_build_query(['_token' => $token, 'connector_id' => $connector->id]),
        'headers' => ['Content-Type: application/x-www-form-urlencoded'],
    ]);
    in_array($r['code'], [302, 200], true) ? ok('POST charging/start') : bad('POST charging/start', $r['code']);

    $r = req('GET', "$base/app/charging");
    $token = csrf($r['body']) ?: $token;
    if ($token) {
        $r = req('POST', "$base/app/charging/stop", [
            'body' => http_build_query(['_token' => $token]),
            'headers' => ['Content-Type: application/x-www-form-urlencoded'],
        ]);
        in_array($r['code'], [302, 200], true) ? ok('POST charging/stop') : bad('POST charging/stop', $r['code']);
    }
} else {
    ok('charging start/stop skipped (active session exists or no connector)');
}

echo implode("\n", $results)."\n\n";
echo $fail === 0 ? "ALL ACTION TESTS PASSED\n" : "FAILED: $fail\n";
exit($fail > 0 ? 1 : 0);
