<?php

namespace App\Providers;

use App\Models\ChargingSession;
use App\Models\Connector;
use App\Models\Payment;
use App\Models\StationWatch;
use App\Observers\ChargingSessionObserver;
use App\Observers\PaymentObserver;
use App\Services\Admin\AdminLiveService;
use App\Services\App\ConnectivityService;
use App\Services\App\CustomerEngagementService;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Blade;
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
        // «وقتی آزاد شد خبرم کن»: آزاد شدن کانکتور فوراً منتظران همان ایستگاه را مطلع می‌کند
        Connector::updated(function (Connector $connector) {
            if ($connector->wasChanged('ocpp_status') && $connector->ocpp_status === 'Available') {
                $stationId = $connector->charger?->station_id;
                if ($stationId && StationWatch::where('station_id', $stationId)->whereNull('notified_at')->exists()) {
                    app(CustomerEngagementService::class)->notifyWatchers($stationId);
                }
            }
        });

        Paginator::defaultView('pagination.admin');
        Paginator::defaultSimpleView('pagination.admin');

        // @v('/assets/x.js') → /assets/x.js?v=<mtime>؛ بعد از هر دیپلوی کش مرورگر نسخه قدیمی JS/CSS را اجرا نکند
        Blade::directive('v', fn ($path) => "<?php echo e({$path}).'?v='.(@filemtime(public_path(ltrim({$path}, '/'))) ?: 0); ?>");

        // Docker / پورت غیراستاندارد: asset() همیشه همان origin مرورگر را بگیرد
        if (! $this->app->runningInConsole() && request()->hasHeader('Host')) {
            URL::forceRootUrl(request()->getSchemeAndHttpHost());
        }

        // شمارنده‌های واقعی منوی پنل مدیریت (به‌جای اعداد ثابت قالب)
        View::composer('admin.layouts.app', function ($view) {
            $view->with('navStats', app(AdminLiveService::class)->nav());
            $user = auth()->user();
            $name = (string) ($user?->name ?: 'مدیر');
            $parts = preg_split('/\s+/u', trim($name)) ?: [$name];
            $view->with('adminMe', [
                'name' => $name,
                'email' => (string) ($user?->email ?? $user?->phone ?? ''),
                'role' => $user?->role?->label() ?? '',
                'initials' => count($parts) > 1 ? mb_substr($parts[0], 0, 1).'.'.mb_substr(end($parts), 0, 1) : mb_substr($name, 0, 2),
            ]);
        });

        $connectivity = fn () => app(ConnectivityService::class)->build(auth()->user());

        View::composer('app.layouts.mobile', fn ($view) => $view->with('scConnectivity', $connectivity()));
        View::composer('app.auth.login', fn ($view) => $view->with('scConnectivity', $connectivity()));
    }
}
