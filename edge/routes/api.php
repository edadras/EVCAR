<?php

use App\Http\Controllers\Api\V1\AppController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\HealthController;
use App\Http\Controllers\Api\V1\OtpController;
use App\Http\Controllers\Api\V1\StationController;
use App\Http\Middleware\AuthenticateAppUser;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::get('health', HealthController::class);
    Route::get('station/config', [StationController::class, 'config']);

    Route::prefix('auth')->group(function () {
        Route::post('lookup', [AuthController::class, 'lookup']);
        Route::post('otp/request', [OtpController::class, 'request']);
        Route::post('otp/verify', [OtpController::class, 'verify']);
    });

    Route::middleware(AuthenticateAppUser::class)->prefix('app')->group(function () {
        Route::get('context', [AppController::class, 'context']);
        Route::get('arrival', [AppController::class, 'arrival']);
        Route::post('arrival/confirm', [AppController::class, 'confirmArrival']);
        Route::post('arrival/cancel', [AppController::class, 'cancelArrival']);
        Route::get('connectors', [AppController::class, 'connectors']);
        Route::get('authorize/status', [AppController::class, 'authorizeStatus']);
        Route::post('authorize', [AppController::class, 'authorize']);
        Route::get('charging/status', [AppController::class, 'chargingStatus']);
        Route::post('charging/start', [AppController::class, 'startCharging']);
        Route::post('charging/stop', [AppController::class, 'stopCharging']);
    });
});
