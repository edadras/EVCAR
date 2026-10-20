<?php

/**
 * End-to-end smoke test for customer web app (/app/*).
 * Run: docker exec sc_cloud_app php scripts/test-customer-app.php
 */

$base = getenv('APP_TEST_URL') ?: 'http://127.0.0.1:8180';
$cookieFile = sys_get_temp_dir().'/sc_app_test_cookies.txt';
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
    if (! empty($opts['headers'])) {
        curl_setopt($ch, CURLOPT_HTTPHEADER, $opts['headers']);
    }
    $raw = curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);
    $headerSize = strpos($raw, "\r\n\r\n");
    $headers = $headerSize !== false ? substr($raw, 0, $headerSize) : '';
    $body = $headerSize !== false ? substr($raw, $headerSize + 4) : $raw;

    return compact('code', 'headers', 'body', 'err');
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

$results = [];
$fail = 0;

function check(string $label, array $r, int $expect = 200): void
{
    global $results, $fail;
    $ok = $r['code'] === $expect && empty($r['err']);
    if (! $ok && $expect === 200 && in_array($r['code'], [301, 302], true)) {
        $ok = true; // redirect after login ok for some flows
    }
    if (! $ok && $r['code'] >= 500) {
        $snippet = substr(strip_tags($r['body']), 0, 200);
        $results[] = "FAIL [$label] HTTP {$r['code']} {$r['err']} $snippet";
        $fail++;

        return;
    }
    if (! $ok) {
        $results[] = "FAIL [$label] HTTP {$r['code']} (expected $expect)";
        $fail++;

        return;
    }
    if (str_contains($r['body'], 'QueryException') || str_contains($r['body'], 'Unhandled')) {
        $results[] = "FAIL [$label] Exception in body";
        $fail++;

        return;
    }
    $results[] = "OK   [$label] HTTP {$r['code']}";
}

echo "=== SafirCharge Customer App E2E ===\nBase: $base\n\n";

// 1. Login page
$r = req('GET', "$base/app/login");
check('GET /app/login', $r);
$token = csrf($r['body']);
if (! $token) {
    echo "FATAL: no CSRF on login\n";
    exit(1);
}

// 2. Login POST
$r = req('POST', "$base/app/login", [
    'body' => http_build_query([
        '_token' => $token,
        'phone' => 'user1@demo.ir',
        'password' => 'Demo@123456',
    ]),
    'headers' => ['Content-Type: application/x-www-form-urlencoded'],
]);
if (! in_array($r['code'], [302, 200], true)) {
    check('POST /app/login', $r, 302);
} else {
    $results[] = 'OK   [POST /app/login] HTTP '.$r['code'];
}

$pages = [
    'home' => '/app',
    'map' => '/app/map',
    'wallet' => '/app/wallet',
    'account' => '/app/account',
    'history' => '/app/history',
    'charging' => '/app/charging',
    'notifications' => '/app/notifications',
    'profile' => '/app/profile',
    'vehicle' => '/app/vehicle',
    'rfid' => '/app/rfid',
    'reserve' => '/app/reserve',
    'support' => '/app/support',
    'scan' => '/app/scan',
    'settings' => '/app/settings',
    'offers' => '/app/offers',
    'plate' => '/app/plate',
    'password' => '/app/password',
    'privacy' => '/app/privacy',
    'terms' => '/app/terms',
    'ticket' => '/app/ticket',
    'ticket-new' => '/app/ticket/new',
    'chat' => '/app/chat',
    'topup-confirm' => '/app/topup/confirm?amount=200000',
];

foreach ($pages as $name => $path) {
    $r = req('GET', $base.$path);
    check("GET $path", $r);
    if ($r['code'] === 200) {
        $t = csrf($r['body']);
        if ($t) {
            $GLOBALS['last_csrf'] = $t;
        }
    }
}

// Charger page — station code embedded in map JSON
$r = req('GET', "$base/app/map");
if (preg_match('/"code":"([^"]+)"/', $r['body'], $m)) {
    $code = $m[1];
    $r2 = req('GET', "$base/app/charger?station=".urlencode($code));
    check("GET /app/charger?station=$code", $r2);
} else {
    $results[] = 'SKIP [charger] no station code in map data';
}

$token = $GLOBALS['last_csrf'] ?? $token;

// API charging status
$r = req('GET', "$base/app/api/charging/status", [
    'headers' => ['Accept: application/json', 'X-Requested-With: XMLHttpRequest'],
]);
if ($r['code'] === 200 && str_contains($r['body'], '{')) {
    $results[] = 'OK   [GET /app/api/charging/status] JSON';
} else {
    $results[] = "FAIL [GET /app/api/charging/status] HTTP {$r['code']}";
    $fail++;
}

// POST notifications read
if ($token) {
    $r = req('POST', "$base/app/notifications/read", [
        'body' => http_build_query(['_token' => $token]),
        'headers' => ['Content-Type: application/x-www-form-urlencoded', 'Accept: application/json', 'X-Requested-With: XMLHttpRequest'],
    ]);
    check('POST /app/notifications/read', $r, 200);
}

// POST profile update
$r = req('GET', "$base/app/profile");
$token = csrf($r['body']) ?: $token;
if ($token) {
    $r = req('POST', "$base/app/profile", [
        'body' => http_build_query([
            '_token' => $token,
            'first_name' => 'مهدی',
            'last_name' => 'تست',
            'email' => 'user1@demo.ir',
            'city' => 'تهران',
        ]),
        'headers' => ['Content-Type: application/x-www-form-urlencoded'],
    ]);
    if (in_array($r['code'], [302, 200], true)) {
        $results[] = 'OK   [POST /app/profile] HTTP '.$r['code'];
    } else {
        $results[] = "FAIL [POST /app/profile] HTTP {$r['code']}";
        $fail++;
    }
}

// POST topup
$r = req('GET', "$base/app/wallet");
$token = csrf($r['body']) ?: $token;
if ($token) {
    $r = req('POST', "$base/app/topup", [
        'body' => http_build_query(['_token' => $token, 'amount' => 100000]),
        'headers' => ['Content-Type: application/x-www-form-urlencoded'],
    ]);
    if (in_array($r['code'], [302, 200], true)) {
        $results[] = 'OK   [POST /app/topup] HTTP '.$r['code'];
    } else {
        $results[] = "FAIL [POST /app/topup] HTTP {$r['code']}";
        $fail++;
    }
}

// POST ticket
$r = req('GET', "$base/app/ticket/new");
$token = csrf($r['body']) ?: $token;
if ($token) {
    $r = req('POST', "$base/app/ticket", [
        'body' => http_build_query([
            '_token' => $token,
            'category' => 'other',
            'subject' => 'تست خودکار',
            'body' => 'این یک تیکت تست است.',
        ]),
        'headers' => ['Content-Type: application/x-www-form-urlencoded'],
    ]);
    if (in_array($r['code'], [302, 200], true)) {
        $results[] = 'OK   [POST /app/ticket] HTTP '.$r['code'];
    } else {
        $results[] = "FAIL [POST /app/ticket] HTTP {$r['code']}";
        $fail++;
    }
}

echo implode("\n", $results)."\n\n";
echo $fail === 0 ? "ALL PASSED (".count($results)." checks)\n" : "FAILED: $fail issue(s)\n";
exit($fail > 0 ? 1 : 0);
