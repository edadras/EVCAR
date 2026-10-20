<?php

use App\Services\App\CustomerEngagementService;
use App\Services\Charging\CloudChargingSessionService;
use App\Services\Charging\ReservationDispatcher;
use App\Services\Finance\SettlementService;
use App\Services\Monitoring\AlertService;
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

// رزرو واقعی روی شارژر (ReserveNow) کمی پیش از شروع بازهٔ رزرو
Artisan::command('reservations:dispatch', function (ReservationDispatcher $dispatcher) {
    $sent = $dispatcher->dispatchDue();
    if ($sent > 0) {
        $this->info("Sent {$sent} ReserveNow command(s).");
    }
})->purpose('Send ReserveNow to chargers for reservations that are about to start');

Schedule::command('reservations:dispatch')->everyMinute()->withoutOverlapping();

// هشدار Edgeهایی که سینک نکرده‌اند
Artisan::command('alerts:check-edges', function (AlertService $alerts) {
    $this->info($alerts->checkEdges().' edge(s) offline.');
})->purpose('Raise alerts for edge controllers that stopped syncing');

Schedule::command('alerts:check-edges')->everyFiveMinutes()->withoutOverlapping();

// تسویهٔ خودکار ماهانهٔ شرکا (روز اول هر ماه)
Artisan::command('settlements:generate {--month= : YYYY-MM}', function (SettlementService $settlements) {
    $this->info($settlements->generateMonthly($this->option('month')).' settlement(s) created.');
})->purpose('Create monthly partner settlements from finished charging sessions');

Schedule::command('settlements:generate')->monthlyOn(1, '02:10');

// اعلان «ایستگاه آزاد شد» (به‌روزرسانی‌های گروهی وضعیت کانکتور رویداد مدل ندارند)
Artisan::command('stations:notify-watchers', function (CustomerEngagementService $engagement) {
    $sent = $engagement->notifyWatchers();
    if ($sent > 0) {
        $this->info("Notified {$sent} customer(s) about free connectors.");
    }
})->purpose('Tell customers waiting on a busy station that a connector became free');

Schedule::command('stations:notify-watchers')->everyMinute()->withoutOverlapping();
