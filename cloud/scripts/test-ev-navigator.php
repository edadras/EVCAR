<?php

use App\Models\User;
use App\Services\Navigator\EvNavigatorService;
use Illuminate\Contracts\Console\Kernel;

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$user = User::where('email', 'user1@demo.ir')->first();
if (! $user) {
    fwrite(STDERR, "demo user not found\n");
    exit(1);
}

$svc = app(EvNavigatorService::class);
$result = $svc->analyze($user, [
    'origin' => ['lat' => 35.689, 'lng' => 51.389],
    'destination' => ['lat' => 32.661, 'lng' => 51.676],
    'vehicle_preset' => 'byd_atto3',
    'current_soc' => 80,
]);

echo json_encode([
    'source' => $result['source'],
    'distance_km' => $result['route']['distance_km'],
    'segments' => count($result['segments']),
    'chargers' => count($result['chargers']),
    'charge_plan' => count($result['charge_plan']),
    'arrival_soc' => $result['summary']['arrival_soc'],
    'soc_profile_points' => count($result['soc_profile'] ?? []),
    'first_charger_route_km' => $result['chargers'][0]['route_km'] ?? null,
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), "\n";
