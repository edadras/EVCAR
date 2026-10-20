<?php

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Services\Sync\CloudCommandPullService;

$stats = app(CloudCommandPullService::class)->pullAndExecute();
echo sprintf("pulled=%d ok=%d failed=%d\n", $stats['pulled'], $stats['ok'], $stats['failed']);
exit($stats['failed'] > 0 ? 1 : 0);
