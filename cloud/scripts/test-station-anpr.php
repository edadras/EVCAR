<?php

use App\Models\Station;
use App\Services\Stations\StationAnprService;
use Illuminate\Contracts\Console\Kernel;

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$station = Station::with('edgeStation')->first();
if (! $station) {
    fwrite(STDERR, "no station\n");
    exit(1);
}

$svc = app(StationAnprService::class);
$panel = $svc->panelData($station);
$summary = $svc->summaryForList($station);

echo json_encode([
    'station' => $station->name,
    'enabled' => $panel['enabled'],
    'cameras' => count($panel['cameras'] ?? []),
    'events' => count($panel['events'] ?? []),
    'summary' => $summary,
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), "\n";
