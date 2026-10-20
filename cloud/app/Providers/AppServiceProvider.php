<?php

namespace App\Providers;

use App\Models\Charger;
use App\Models\ChargingSession;
use App\Models\OperationalAlert;
use App\Models\Payment;
use App\Models\SupportTicket;
use App\Observers\ChargingSessionObserver;
use App\Observers\PaymentObserver;
use App\Services\App\ConnectivityService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        ChargingSession::observe(ChargingSessionObserver::class);
        Payment::observe(PaymentObserver::class);

        // Docker / پورت غیراستاندارد: asset() همیشه همان origin مرورگر را بگیرد
        if (! $this->app->runningInConsole() && request()->hasHeader('Host')) {
            URL::forceRootUrl(request()->getSchemeAndHttpHost());
        }

        // شمارنده‌های واقعی منوی پنل مدیریت (به‌جای اعداد ثابت قالب)
        View::composer('admin.layouts.app', function ($view) {
            $view->with('navStats', Cache::remember('admin.nav_stats', 30, fn () => [
                'sessions' => ChargingSession::where('status', 'active')->count(),
                'support' => SupportTicket::whereIn('status', ['open', 'in_progress'])->count(),
                'notifications' => OperationalAlert::where('status', 'open')->count(),
                'online' => Charger::where('is_online', true)->count(),
                'chargers' => Charger::count(),
            ]));
        });

        $connectivity = fn () => app(ConnectivityService::class)->build(auth()->user());

        View::composer('app.layouts.mobile', fn ($view) => $view->with('scConnectivity', $connectivity()));
        View::composer('app.auth.login', fn ($view) => $view->with('scConnectivity', $connectivity()));
    }
}
