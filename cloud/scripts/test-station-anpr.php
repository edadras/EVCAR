<?php

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$station = App\Models\Station::with('edgeStation')->first();
if (! $station) {
    fwrite(STDERR, "no station\n");
    exit(1);
}

$svc = app(App\Services\Stations\StationAnprService::class);
$panel = $svc->panelData($station);
$summary = $svc->summaryForList($station);

echo json_encode([
    'station' => $station->name,
    'enabled' => $panel['enabled'],
    'cameras' => count($panel['cameras'] ?? []),
    'events' => count($panel['events'] ?? []),
    'summary' => $summary,
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), "\n";
