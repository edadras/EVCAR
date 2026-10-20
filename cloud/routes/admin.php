<?php

use App\Http\Controllers\Admin\AlertController;
use App\Http\Controllers\Admin\ApiController;
use App\Http\Controllers\Admin\AuthController;
use App\Http\Controllers\Admin\ChargerController;
use App\Http\Controllers\Admin\ChargingSessionController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\EvCatalogVehicleController;
use App\Http\Controllers\Admin\InsightsController;
use App\Http\Controllers\Admin\NotificationController;
use App\Http\Controllers\Admin\PageController;
use App\Http\Controllers\Admin\PartnerController;
use App\Http\Controllers\Admin\QrTokenController;
use App\Http\Controllers\Admin\RemoteCommandController;
use App\Http\Controllers\Admin\RfidCardController;
use App\Http\Controllers\Admin\SettingsController;
use App\Http\Controllers\Admin\SettlementController;
use App\Http\Controllers\Admin\StationController;
use App\Http\Controllers\Admin\SupportCenterController;
use App\Http\Controllers\Admin\TariffController;
use App\Http\Controllers\Admin\UserController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('login', [AuthController::class, 'login']);
});

Route::middleware(['auth', 'staff'])->group(function () {
    Route::post('logout', [AuthController::class, 'logout'])->name('logout');
    Route::get('/', DashboardController::class)->name('dashboard');

    Route::prefix('api')->name('api.')->group(function () {
        Route::get('search', [ApiController::class, 'search'])->name('search');
        Route::get('users', [ApiController::class, 'users'])->name('users');
        Route::post('wallet/topup', [ApiController::class, 'walletTopup'])->name('wallet.topup');
        Route::post('wallet/refunds/{ticket}/approve', [ApiController::class, 'approveRefund'])->name('wallet.refunds.approve');
        Route::post('wallet/refunds/{ticket}/reject', [ApiController::class, 'rejectRefund'])->name('wallet.refunds.reject');
        Route::post('reservations', [ApiController::class, 'storeReservation'])->name('reservations.store');
        Route::post('reservations/{reservation}/cancel', [ApiController::class, 'cancelReservation'])->name('reservations.cancel');
        Route::post('repairs', [ApiController::class, 'storeRepair'])->name('repairs.store');
        Route::post('inventory/in', [ApiController::class, 'storeInventoryIn'])->name('inventory.in');
        Route::post('inventory/assign', [ApiController::class, 'storeInventoryAssign'])->name('inventory.assign');
        Route::post('campaigns', [ApiController::class, 'storeCampaign'])->name('campaigns.store');
        Route::post('staff', [ApiController::class, 'storeStaff'])->name('staff.store');
        Route::get('anpr/live', [ApiController::class, 'anprLive'])->name('anpr.live');
        Route::post('stations/{station}/remote-command', [RemoteCommandController::class, 'store'])->name('remote-command.store');
        Route::get('stations/{station}/remote-commands', [RemoteCommandController::class, 'recent'])->name('remote-command.recent');
        Route::get('stations/{station}/remote-live', [RemoteCommandController::class, 'live'])->name('remote-command.live');
        Route::get('reports/data', [ApiController::class, 'reportsData'])->name('reports.data');
        Route::post('sessions/{session}/stop', [ApiController::class, 'stopSession'])->name('sessions.stop');
    });

    // مدیریت شبکه
    Route::get('monitoring', [PageController::class, 'monitoring'])->name('monitoring');
    Route::get('alerts', [AlertController::class, 'index'])->name('alerts.index');
    Route::post('alerts/{alert}/resolve', [AlertController::class, 'resolve'])->name('alerts.resolve');
    Route::get('insights', [InsightsController::class, 'index'])->name('insights');
    Route::get('exports/{type}', [InsightsController::class, 'export'])->name('exports')->where('type', 'sessions|revenue|tax|settlements');
    Route::resource('stations', StationController::class)->except(['destroy']);
    Route::get('stations/{station}/chargers/create', [ChargerController::class, 'create'])->name('stations.chargers.create');
    Route::post('stations/{station}/chargers', [ChargerController::class, 'store'])->name('stations.chargers.store');
    Route::get('chargers/{charger}', [ChargerController::class, 'show'])->name('chargers.show');
    Route::get('sessions', [ChargingSessionController::class, 'index'])->name('sessions.index');
    Route::get('reservations', [PageController::class, 'reservations'])->name('reservations');
    Route::get('anpr', [PageController::class, 'anpr'])->name('anpr');
    Route::get('remote', [PageController::class, 'remote'])->name('remote');

    // مالی و پرداخت
    Route::get('wallet', [PageController::class, 'wallet'])->name('wallet');
    Route::get('finance', [PageController::class, 'finance'])->name('finance');
    Route::get('reports', [PageController::class, 'reports'])->name('reports');
    Route::get('tariffs', [PageController::class, 'tariffs'])->name('tariffs.index');
    Route::get('tariffs/create', [TariffController::class, 'create'])->name('tariffs.create');
    Route::post('tariffs', [TariffController::class, 'store'])->name('tariffs.store');
    Route::get('tariffs/{tariff}/edit', [TariffController::class, 'edit'])->name('tariffs.edit');
    Route::put('tariffs/{tariff}', [TariffController::class, 'update'])->name('tariffs.update');
    Route::delete('tariffs/{tariff}', [TariffController::class, 'destroy'])->name('tariffs.destroy');
    Route::redirect('rfid', '/admin/tariffs?tab=rfid');
    Route::get('rfid/create', [RfidCardController::class, 'create'])->name('rfid.create');
    Route::post('rfid', [RfidCardController::class, 'store'])->name('rfid.store');
    Route::get('rfid/{rfid}/edit', [RfidCardController::class, 'edit'])->name('rfid.edit');
    Route::put('rfid/{rfid}', [RfidCardController::class, 'update'])->name('rfid.update');
    Route::delete('rfid/{rfid}', [RfidCardController::class, 'destroy'])->name('rfid.destroy');
    Route::post('rfid/{rfid}/status', [RfidCardController::class, 'updateStatus'])->name('rfid.status');
    Route::get('settlement', [PageController::class, 'settlement'])->name('settlement');
    Route::get('settlement/create', [SettlementController::class, 'create'])->name('settlement.create');
    Route::post('settlement', [SettlementController::class, 'store'])->name('settlement.store');

    // عملیات
    Route::get('users', [PageController::class, 'users'])->name('users.index');
    Route::get('users/create', [UserController::class, 'create'])->name('users.create');
    Route::post('users', [UserController::class, 'store'])->name('users.store');
    Route::get('partners', [PageController::class, 'partners'])->name('partners.index');
    Route::get('partners/create', [PartnerController::class, 'create'])->name('partners.create');
    Route::post('partners', [PartnerController::class, 'store'])->name('partners.store');
    Route::get('support', [SupportCenterController::class, 'index'])->name('support');
    Route::get('support/tickets/{ticket}', [SupportCenterController::class, 'show'])->name('support.ticket');
    Route::post('support/tickets/{ticket}/reply', [SupportCenterController::class, 'reply'])->name('support.reply');
    Route::post('support/tickets/{ticket}/update', [SupportCenterController::class, 'update'])->name('support.update');
    Route::post('support/tickets/{ticket}/credit', [SupportCenterController::class, 'credit'])->name('support.credit');
    Route::post('support/tickets/{ticket}/draft', [SupportCenterController::class, 'draft'])->name('support.draft');
    Route::post('support/canned', [SupportCenterController::class, 'storeCanned'])->name('support.canned.store');
    Route::get('support/reviews', [SupportCenterController::class, 'reviews'])->name('support.reviews');
    Route::post('support/reviews/{review}', [SupportCenterController::class, 'updateReview'])->name('support.reviews.update');
    Route::delete('support/canned/{index}', [SupportCenterController::class, 'destroyCanned'])->name('support.canned.destroy')->whereNumber('index');
    Route::get('support/ai', [SupportCenterController::class, 'conversations'])->name('support.ai');
    Route::get('support/ai/{conversation:uuid}', [SupportCenterController::class, 'conversation'])->name('support.ai.show');
    Route::post('support/ai/settings', [SupportCenterController::class, 'updateAiSettings'])->name('support.ai.settings');

    // انبار و دارایی
    Route::get('inventory', [PageController::class, 'inventory'])->name('inventory');
    Route::get('repairs', [PageController::class, 'repairs'])->name('repairs');

    // سامانه و ارتباطات
    Route::get('sync', [PageController::class, 'sync'])->name('sync.index');
    Route::get('sync/settings', [PageController::class, 'syncSettings'])->name('sync.settings');
    Route::post('sync/settings', [SettingsController::class, 'updateSync'])->name('sync.settings.update');
    Route::get('notifications', [PageController::class, 'notifications'])->name('notifications.index');
    Route::get('notifications/create', [NotificationController::class, 'create'])->name('notifications.create');
    Route::post('notifications', [NotificationController::class, 'store'])->name('notifications.store');
    Route::get('campaigns', [PageController::class, 'campaigns'])->name('campaigns');
    Route::get('team', [PageController::class, 'team'])->name('team');
    Route::get('settings', [PageController::class, 'settings'])->name('settings');
    Route::post('settings', [SettingsController::class, 'update'])->name('settings.update');
    Route::resource('ev-catalog', EvCatalogVehicleController::class)->except(['show']);

    // QR
    Route::redirect('qr', '/admin/tariffs?tab=qr');
    Route::get('qr/create', [QrTokenController::class, 'create'])->name('qr.create');
    Route::post('qr', [QrTokenController::class, 'store'])->name('qr.store');
    Route::delete('qr/{qr}', [QrTokenController::class, 'destroy'])->name('qr.destroy');
});
