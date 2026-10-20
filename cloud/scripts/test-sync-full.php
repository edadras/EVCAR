<?php

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\User;
use App\Models\Vehicle;
use App\Services\Sync\EdgeSyncPayloadBuilder;
use App\Models\EdgeStation;

$fail = 0;
$results = [];
function ok(string $m): void { global $results; $results[] = "OK   $m"; }
function bad(string $m): void { global $results, $fail; $results[] = "FAIL $m"; $fail++; }

echo "=== Full Sync Tests ===\n";

$edge = EdgeStation::where('edge_code', 'EDGE-AZADI-01')->first();
if (! $edge) {
    bad('EDGE-AZADI-01 missing');
    exit(1);
}

$builder = app(EdgeSyncPayloadBuilder::class);
$snapshot = $builder->buildSnapshot($edge);

! empty($snapshot['users']) ? ok('snapshot users') : bad('snapshot users');
! empty($snapshot['chargers']) ? ok('snapshot chargers') : bad('snapshot chargers');
! empty($snapshot['tariffs']) ? ok('snapshot tariffs') : bad('snapshot tariffs');
isset($snapshot['qr_tokens']) ? ok('snapshot qr_tokens key') : bad('snapshot qr_tokens key');

$user = User::where('email', 'user1@demo.ir')->first();
$vehicleCount = $user->vehicles()->count();
$vehicleCount > 0 ? ok('demo user has vehicles') : bad('demo user vehicles');

$delta = $builder->buildDelta($edge, now()->subMinute());
isset($delta['mode']) && $delta['mode'] === 'delta' ? ok('delta mode') : bad('delta mode');
isset($delta['removed']) ? ok('delta removed key') : bad('delta removed');

foreach ($results as $r) {
    echo "$r\n";
}
echo $fail ? "\nFAILED ($fail)\n" : "\nALL SYNC TESTS PASSED\n";
exit($fail ? 1 : 0);
