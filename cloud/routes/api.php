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
