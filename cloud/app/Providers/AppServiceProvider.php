<?php

namespace App\Providers;

use App\Models\ChargingSession;
use App\Models\Payment;
use App\Observers\ChargingSessionObserver;
use App\Observers\PaymentObserver;
use App\Services\App\ConnectivityService;
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

        $connectivity = fn () => app(ConnectivityService::class)->build(auth()->user());

        View::composer('app.layouts.mobile', fn ($view) => $view->with('scConnectivity', $connectivity()));
        View::composer('app.auth.login', fn ($view) => $view->with('scConnectivity', $connectivity()));
    }
}
