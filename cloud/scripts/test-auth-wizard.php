<?php

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

use App\Models\User;
use App\Services\Auth\CustomerAuthService;
use Illuminate\Contracts\Console\Kernel;

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

echo "=== Auth Wizard Tests ===\n";

$svc = app(CustomerAuthService::class);
$user = User::where('email', 'user1@demo.ir')->first();

$lookup = $svc->lookup($user->phone);
$lookup['registered'] && $lookup['has_password'] ? ok('lookup existing user') : bad('lookup existing user');

$lookupNew = $svc->lookup('09999888777');
! $lookupNew['registered'] ? ok('lookup new phone') : bad('lookup new phone');

$req = $svc->requestOtp('09999888777', 'register');
! empty($req['debug_code']) ? ok('otp request dev code') : bad('otp request');

$ver = $svc->verifyOtp('09999888777', '111111', 'register');
! empty($ver['registration_token']) ? ok('verify test otp') : bad('verify test otp');

try {
    $svc->register('09999888777', 'Test@12345', $ver['registration_token']);
    ok('register new user');
    User::where('phone', '09999888777')->delete();
} catch (Throwable $e) {
    bad('register: '.$e->getMessage());
}

try {
    $svc->login($user->phone, 'Demo@123456');
    ok('login demo user');
} catch (Throwable $e) {
    bad('login: '.$e->getMessage());
}

$codes = $svc->testOtpCodes();
count($codes) >= 3 ? ok('test otp codes ('.count($codes).')') : bad('test otp codes');

foreach ($results as $r) {
    echo "$r\n";
}
echo $fail ? "\nFAILED ($fail)\n" : "\nALL AUTH TESTS PASSED\n";
exit($fail ? 1 : 0);
