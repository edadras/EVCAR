<?php

use App\Http\Middleware\AuthenticateEdgeStation;
use App\Http\Middleware\EnsureCustomerUser;
use App\Http\Middleware\EnsurePartnerUser;
use App\Http\Middleware\EnsureStaffUser;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->trustProxies(at: env('TRUSTED_PROXIES', '127.0.0.1,172.16.0.0/12,10.0.0.0/8,192.168.0.0/16'));

        $middleware->alias([
            'staff' => EnsureStaffUser::class,
            'partner' => EnsurePartnerUser::class,
            'customer' => EnsureCustomerUser::class,
            'edge' => AuthenticateEdgeStation::class,
        ]);

        $middleware->redirectGuestsTo(function (Request $request): string {
            if ($request->is('partner', 'partner/*')) {
                return route('partner.login');
            }
            if ($request->is('app', 'app/*')) {
                return route('app.login');
            }

            return route('admin.login');
        });
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*')
                || $request->is('admin/api/*')
                || $request->expectsJson(),
        );
    })->create();
