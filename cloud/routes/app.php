<?php

use App\Http\Controllers\App\ActionController;
use App\Http\Controllers\App\ApiController;
use App\Http\Controllers\App\AssistantController;
use App\Http\Controllers\App\AuthApiController;
use App\Http\Controllers\App\AuthController;
use App\Http\Controllers\App\NavigatorController;
use App\Http\Controllers\App\PageController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('login', [AuthController::class, 'login']);

    Route::prefix('api/auth')->name('api.auth.')->group(function () {
        Route::post('lookup', [AuthApiController::class, 'lookup'])->name('lookup');
        Route::post('otp/request', [AuthApiController::class, 'requestOtp'])->name('otp.request');
        Route::post('otp/verify', [AuthApiController::class, 'verifyOtp'])->name('otp.verify');
        Route::post('register', [AuthApiController::class, 'register'])->name('register');
        Route::post('login', [AuthApiController::class, 'login'])->name('login');
    });
});

Route::middleware(['auth', 'customer'])->group(function () {
    Route::post('logout', [AuthController::class, 'logout'])->name('logout');
    Route::get('/', [PageController::class, 'home'])->name('home');

    Route::get('map', [PageController::class, 'map'])->name('map');
    Route::get('navigator', [NavigatorController::class, 'index'])->name('navigator');
    Route::post('api/navigator/analyze', [NavigatorController::class, 'analyze'])->name('api.navigator.analyze');
    Route::post('api/navigator/preview', [NavigatorController::class, 'preview'])->name('api.navigator.preview');
    Route::get('wallet', [PageController::class, 'wallet'])->name('wallet');
    Route::get('account', [PageController::class, 'account'])->name('account');
    Route::get('history', [PageController::class, 'history'])->name('history');
    Route::get('charging', [PageController::class, 'charging'])->name('charging');
    Route::post('charging/start', [ActionController::class, 'startCharging'])->name('charging.start');
    Route::post('charging/stop', [ActionController::class, 'stopCharging'])->name('charging.stop');
    Route::get('api/context', [ApiController::class, 'context'])->name('api.context');
    Route::get('api/arrival', [ApiController::class, 'arrival'])->name('api.arrival');
    Route::post('api/arrival/confirm', [ApiController::class, 'confirmArrival'])->name('api.arrival.confirm');
    Route::post('api/arrival/cancel', [ApiController::class, 'cancelArrival'])->name('api.arrival.cancel');
    Route::get('api/charging/status', [ApiController::class, 'chargingStatus'])->name('api.charging.status');
    Route::get('notifications', [PageController::class, 'notifications'])->name('notifications');
    Route::post('notifications/read', [ActionController::class, 'markNotificationsRead'])->name('notifications.read');
    Route::get('profile', [PageController::class, 'profile'])->name('profile');
    Route::post('profile', [ActionController::class, 'updateProfile'])->name('profile.update');
    Route::get('vehicle', [PageController::class, 'vehicle'])->name('vehicle');
    Route::post('vehicle', [ActionController::class, 'storeVehicle'])->name('vehicle.store');
    Route::put('vehicle/{vehicle}', [ActionController::class, 'updateVehicle'])->name('vehicle.update');
    Route::delete('vehicle/{vehicle}', [ActionController::class, 'destroyVehicle'])->name('vehicle.destroy');
    Route::post('vehicle/{vehicle}/default', [ActionController::class, 'setDefaultVehicle'])->name('vehicle.default');
    Route::get('rfid', [PageController::class, 'rfid'])->name('rfid');
    Route::post('rfid', [ActionController::class, 'storeRfid'])->name('rfid.store');
    Route::put('rfid/{card}', [ActionController::class, 'updateRfid'])->name('rfid.update');
    Route::delete('rfid/{card}', [ActionController::class, 'destroyRfid'])->name('rfid.destroy');
    Route::post('rfid/{card}/block', [ActionController::class, 'blockRfid'])->name('rfid.block');
    Route::post('rfid/{card}/activate', [ActionController::class, 'activateRfid'])->name('rfid.activate');
    Route::get('reserve', [PageController::class, 'reserve'])->name('reserve');
    Route::post('reserve', [ActionController::class, 'storeReservation'])->name('reserve.store');
    Route::get('support', [PageController::class, 'support'])->name('support');
    Route::get('assistant', [AssistantController::class, 'page'])->name('assistant');
    Route::get('api/assistant', [AssistantController::class, 'show'])->name('api.assistant');
    Route::post('api/assistant', [AssistantController::class, 'send'])->name('api.assistant.send');
    Route::post('api/assistant/escalate', [AssistantController::class, 'escalate'])->name('api.assistant.escalate');
    Route::post('api/assistant/reset', [AssistantController::class, 'reset'])->name('api.assistant.reset');
    Route::get('scan', [PageController::class, 'scan'])->name('scan');
    Route::get('charger', [PageController::class, 'charger'])->name('charger');
    Route::get('arrival', [PageController::class, 'arrival'])->name('arrival');
    Route::get('settings', [PageController::class, 'settings'])->name('settings');
    Route::post('settings', [ActionController::class, 'updateSettings'])->name('settings.update');
    Route::get('offers', [PageController::class, 'offers'])->name('offers');
    Route::get('plate', [PageController::class, 'plate'])->name('plate');
    Route::post('plate', [ActionController::class, 'updatePlate'])->name('plate.update');
    Route::get('password', [PageController::class, 'password'])->name('password');
    Route::post('password', [ActionController::class, 'updatePassword'])->name('password.update');
    Route::get('privacy', [PageController::class, 'privacy'])->name('privacy');
    Route::get('terms', [PageController::class, 'terms'])->name('terms');
    Route::get('ticket', [PageController::class, 'ticket'])->name('ticket');
    Route::get('ticket/new', [PageController::class, 'ticketNew'])->name('ticket.new');
    Route::post('ticket', [ActionController::class, 'storeTicket'])->name('ticket.store');
    Route::get('chat', [ActionController::class, 'chat'])->name('chat');
    Route::post('chat', [ActionController::class, 'storeChatMessage'])->name('chat.store');
    Route::get('topup/confirm', [PageController::class, 'topupConfirm'])->name('topup.confirm');
    Route::post('topup', [ActionController::class, 'processTopup'])->name('topup.process');
    Route::get('topup/result', [PageController::class, 'topupResult'])->name('topup.result');
});
