<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\EdgeCommandsController;
use App\Http\Controllers\Api\V1\EdgeDeltaController;
use App\Http\Controllers\Api\V1\EdgeSnapshotController;
use App\Http\Controllers\Api\V1\HealthController;
use App\Http\Controllers\Api\V1\StationController;
use App\Http\Controllers\Api\V1\SyncPushController;
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

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('auth/logout', [AuthController::class, 'logout']);
        Route::get('auth/me', [AuthController::class, 'me']);
    });
});

// OCPI 2.2.1 (رومینگ) — CPO فقط خواندنی
Route::prefix('ocpi')->middleware(\App\Http\Middleware\AuthenticateOcpi::class)->group(function () {
    Route::get('versions', [\App\Http\Controllers\Ocpi\OcpiController::class, 'versions']);
    Route::get('2.2.1', [\App\Http\Controllers\Ocpi\OcpiController::class, 'details']);
    Route::get('2.2.1/locations', [\App\Http\Controllers\Ocpi\OcpiController::class, 'locations']);
    Route::get('2.2.1/locations/{code}', [\App\Http\Controllers\Ocpi\OcpiController::class, 'showLocation']);
    Route::get('2.2.1/tariffs', [\App\Http\Controllers\Ocpi\OcpiController::class, 'tariffs']);
    Route::get('2.2.1/sessions', [\App\Http\Controllers\Ocpi\OcpiController::class, 'sessions']);
});
