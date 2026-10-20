<?php

use App\Http\Controllers\Partner\AuthController;
use App\Http\Controllers\Partner\DashboardController;
use App\Http\Controllers\Partner\PageController;
use Illuminate\Support\Facades\Route;

Route::middleware('panel.guest:partner')->group(function () {
    Route::get('login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('login', [AuthController::class, 'login']);
});

Route::middleware(['auth', 'partner'])->group(function () {
    Route::post('logout', [AuthController::class, 'logout'])->name('logout');
    Route::get('/', DashboardController::class)->name('dashboard');
    Route::get('stations', [PageController::class, 'stations'])->name('stations');
    Route::get('reports', [PageController::class, 'reports'])->name('reports');
    Route::get('payments', [PageController::class, 'payments'])->name('payments');
    Route::get('support', [PageController::class, 'support'])->name('support');
    Route::get('profile', [PageController::class, 'profile'])->name('profile');
});
