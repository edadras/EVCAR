<?php

use App\Http\Controllers\Panel\ApiController;
use App\Http\Controllers\Panel\AuthController;
use App\Http\Controllers\Panel\PageController;
use App\Http\Controllers\Panel\SetupApiController;
use App\Http\Middleware\EnsureStationOperator;
use Illuminate\Support\Facades\Route;

Route::prefix('panel')->name('panel.')->group(function () {
    Route::middleware('guest')->group(function () {
        Route::get('login', [AuthController::class, 'showLogin'])->name('login');
        Route::post('login', [AuthController::class, 'login'])->name('login.submit');
    });

    Route::middleware(['auth', EnsureStationOperator::class])->group(function () {
        Route::post('logout', [AuthController::class, 'logout'])->name('logout');
        Route::get('/', [PageController::class, 'dashboard'])->name('dashboard');
        Route::get('remote', [PageController::class, 'remote'])->name('remote');
        Route::get('sync', [PageController::class, 'sync'])->name('sync');
        Route::get('sessions', [PageController::class, 'sessions'])->name('sessions');
        Route::get('setup', [PageController::class, 'setup'])->name('setup');

        Route::prefix('api')->name('api.')->group(function () {
            Route::get('live', [ApiController::class, 'live'])->name('live');
            Route::post('commands', [ApiController::class, 'storeCommand'])->name('commands.store');
            Route::get('commands/recent', [ApiController::class, 'recentCommands'])->name('commands.recent');
            Route::get('sync/status', [ApiController::class, 'syncStatus'])->name('sync.status');
            Route::get('sync/feed', [ApiController::class, 'syncFeed'])->name('sync.feed');
            Route::get('sync/outbox', [ApiController::class, 'syncOutbox'])->name('sync.outbox');
            Route::get('sync/cached', [ApiController::class, 'syncCached'])->name('sync.cached');
            Route::get('sync/activity', [ApiController::class, 'syncActivity'])->name('sync.activity');
            Route::post('sync/pull', [ApiController::class, 'syncPull'])->name('sync.pull');
            Route::post('sync/push', [ApiController::class, 'syncPush'])->name('sync.push');
            Route::post('sync/pull-commands', [ApiController::class, 'syncPullCommands'])->name('sync.pull-commands');
            Route::post('sessions/{sessionUuid}/stop', [ApiController::class, 'stopSession'])->name('sessions.stop');

            Route::prefix('setup')->name('setup.')->group(function () {
                Route::get('/', [SetupApiController::class, 'show'])->name('show');
                Route::put('station', [SetupApiController::class, 'updateStation'])->name('station');
                Route::put('cloud', [SetupApiController::class, 'updateCloud'])->name('cloud');
                Route::put('ocpp', [SetupApiController::class, 'updateOcpp'])->name('ocpp');
                Route::post('ocpp/test', [SetupApiController::class, 'testOcpp'])->name('ocpp.test');
                Route::put('anpr', [SetupApiController::class, 'updateAnpr'])->name('anpr');
                Route::post('anpr/health', [SetupApiController::class, 'testAnprHealth'])->name('anpr.health');
                Route::post('anpr/camera/start', [SetupApiController::class, 'startAnprCamera'])->name('anpr.camera');
                Route::post('anpr/test', [SetupApiController::class, 'testAnprRecognize'])->name('anpr.test');
                Route::put('gsm', [SetupApiController::class, 'updateGsm'])->name('gsm');
                Route::post('gsm/test-otp', [SetupApiController::class, 'testGsmOtp'])->name('gsm.test');
                Route::post('devices/sync', [SetupApiController::class, 'syncDevices'])->name('devices.sync');
                Route::put('chargers/{charger}', [SetupApiController::class, 'updateCharger'])->name('chargers.update');
                Route::put('connectors/{connector}', [SetupApiController::class, 'updateConnector'])->name('connectors.update');
            });
        });
    });
});
