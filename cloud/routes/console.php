<?php

use App\Services\Charging\CloudChargingSessionService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// درخواست‌های شروع شارژی که Edge به آن‌ها پاسخ نداد (Edge آفلاین) بدون هزینه بسته می‌شوند.
Artisan::command('charging:expire-requests', function (CloudChargingSessionService $charging) {
    $expired = $charging->expireStaleRequests();
    if ($expired > 0) {
        $this->info("Expired {$expired} unanswered remote start request(s).");
    }
})->purpose('Close remote-start requests that the station edge never picked up');

Schedule::command('charging:expire-requests')->everyMinute()->withoutOverlapping();
