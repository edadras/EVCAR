<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('sync:auto')->everyMinute()->withoutOverlapping(5);
// دستورهای ابر (شروع/توقف شارژ از اپ آنلاین) باید سریع اجرا شوند.
Schedule::command('sync:pull-commands')->everyFiveSeconds()->withoutOverlapping(2);
Schedule::command('anpr:watch-auto-start')->everyThirtySeconds();
// پشتیبان روزانهٔ پایگاه دادهٔ ایستگاه
Schedule::command('edge:backup')->dailyAt('03:15')->withoutOverlapping();
