<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\EdgeCommandsController;
use App\Http\Controllers\Api\V1\EdgeDeltaController;
use App\Http\Controllers\Api\V1\EdgeSnapshotController;
use App\Http\Controllers\Api\V1\HealthController;
use App\Http\Controllers\Api\V1\MobileApiController;
use App\Http\Controllers\Api\V1\StationController;
use App\Http\Controllers\Api\V1\SyncPushController;
use App\Http\Controllers\App\ActionController;
use App\Http\Controllers\App\ApiController;
use App\Http\Controllers\App\AuthApiController;
use App\Http\Controllers\App\NavigatorController;
use App\Http\Controllers\Ocpi\OcpiController;
use App\Http\Middleware\AuthenticateOcpi;
use App\Http\Middleware\MobileJsonResponses;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::get('health', HealthController::class);

    Route::post('auth/login', [AuthController::class, 'login']);
    Route::get('stations', [StationController::class, 'index']);
    Route::get('stations/{station:uuid}', [StationController::class, 'show']);

    Route::middleware('edge')->group(function () {
        Route::post('sync/push', [SyncPushController::class, 'store']);
        Route::get('sync/snapshot', [EdgeSnapshotController::class, 'show']);
        Route::get('sync/delta', [EdgeDeltaController::class, 'show']);
        Route::get('sync/commands', [EdgeCommandsController::class, 'index']);
        Route::post('sync/commands/{uuid}/ack', [EdgeCommandsController::class, 'acknowledge']);
    });

    // اپ موبایل مشتری (Flutter — اندروید و iOS). همان منطق وب‌اپ /app با توکن Sanctum.
    Route::prefix('mobile')->group(function () {
        Route::middleware('throttle:30,1')->prefix('auth')->group(function () {
            Route::post('lookup', [AuthApiController::class, 'lookup']);
            Route::post('otp/request', [AuthApiController::class, 'requestOtp']);
            Route::post('otp/verify', [AuthApiController::class, 'verifyOtp']);
            Route::post('register', [MobileApiController::class, 'register']);
            Route::post('login', [MobileApiController::class, 'login']);
        });
        Route::get('legal/{key}', [MobileApiController::class, 'legal']);

        Route::middleware(['auth:sanctum', MobileJsonResponses::class])->group(function () {
            Route::post('auth/logout', [MobileApiController::class, 'logout']);
            Route::get('me', [MobileApiController::class, 'me']);
            Route::post('devices', [MobileApiController::class, 'registerDevice']);
            Route::delete('devices', [MobileApiController::class, 'unregisterDevice']);
            Route::post('location', [MobileApiController::class, 'updateLocation']);
            Route::post('favorites/{code}', [MobileApiController::class, 'toggleFavorite']);
            Route::get('notifications/since', [MobileApiController::class, 'notificationsSince']);

            Route::get('home', [MobileApiController::class, 'home']);
            Route::get('map', [MobileApiController::class, 'map']);
            Route::get('charger', [MobileApiController::class, 'charger']);
            Route::get('scan', [MobileApiController::class, 'scan']);
            Route::get('charging', [MobileApiController::class, 'charging']);
            Route::get('charging/status', [ApiController::class, 'chargingStatus']);
            Route::post('charging/start', [ActionController::class, 'startCharging']);
            Route::post('charging/stop', [ActionController::class, 'stopCharging']);
            Route::get('wallet', [MobileApiController::class, 'wallet']);
            Route::post('topup', [ActionController::class, 'processTopup']);
            Route::get('payments/{uuid}', [MobileApiController::class, 'payment']);
            Route::get('history', [MobileApiController::class, 'history']);
            Route::get('account', [MobileApiController::class, 'account']);
            Route::post('profile', [ActionController::class, 'updateProfile']);
            Route::post('password', [ActionController::class, 'updatePassword']);
            Route::post('plate', [ActionController::class, 'updatePlate']);
            Route::get('notifications', [MobileApiController::class, 'notifications']);
            Route::post('notifications/read', [ActionController::class, 'markNotificationsRead']);
            Route::get('vehicles', [MobileApiController::class, 'vehicles']);
            Route::post('vehicles', [ActionController::class, 'storeVehicle']);
            Route::put('vehicles/{vehicle}', [ActionController::class, 'updateVehicle']);
            Route::delete('vehicles/{vehicle}', [ActionController::class, 'destroyVehicle']);
            Route::post('vehicles/{vehicle}/default', [ActionController::class, 'setDefaultVehicle']);
            Route::get('rfid', [MobileApiController::class, 'rfid']);
            Route::post('rfid', [ActionController::class, 'storeRfid']);
            Route::put('rfid/{card}', [ActionController::class, 'updateRfid']);
            Route::delete('rfid/{card}', [ActionController::class, 'destroyRfid']);
            Route::post('rfid/{card}/block', [ActionController::class, 'blockRfid']);
            Route::post('rfid/{card}/activate', [ActionController::class, 'activateRfid']);
            Route::get('reservations', [MobileApiController::class, 'reservations']);
            Route::post('reservations', [ActionController::class, 'storeReservation']);
            Route::get('support', [MobileApiController::class, 'support']);
            Route::get('tickets/form', [MobileApiController::class, 'ticketForm']);
            Route::get('tickets/{id}', [MobileApiController::class, 'ticket'])->whereNumber('id');
            Route::post('tickets', [ActionController::class, 'storeTicket']);
            Route::post('chat', [ActionController::class, 'storeChatMessage']);
            Route::get('settings', [MobileApiController::class, 'settings']);
            Route::post('settings', [ActionController::class, 'updateSettings']);
            Route::get('offers', [MobileApiController::class, 'offers']);
            Route::get('context', [MobileApiController::class, 'context']);
            Route::get('arrival', [ApiController::class, 'arrival']);
            Route::post('arrival/confirm', [ApiController::class, 'confirmArrival']);
            Route::post('arrival/cancel', [ApiController::class, 'cancelArrival']);
            Route::get('navigator', [MobileApiController::class, 'navigator']);
            Route::post('navigator/analyze', [NavigatorController::class, 'analyze']);
            Route::post('navigator/preview', [NavigatorController::class, 'preview']);
        });
    });

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('auth/logout', [AuthController::class, 'logout']);
        Route::get('auth/me', [AuthController::class, 'me']);
    });
});

// OCPI 2.2.1 (رومینگ) — CPO فقط خواندنی
Route::prefix('ocpi')->middleware(AuthenticateOcpi::class)->group(function () {
    Route::get('versions', [OcpiController::class, 'versions']);
    Route::get('2.2.1', [OcpiController::class, 'details']);
    Route::get('2.2.1/locations', [OcpiController::class, 'locations']);
    Route::get('2.2.1/locations/{code}', [OcpiController::class, 'showLocation']);
    Route::get('2.2.1/tariffs', [OcpiController::class, 'tariffs']);
    Route::get('2.2.1/sessions', [OcpiController::class, 'sessions']);
});
