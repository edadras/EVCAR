<?php

use App\Http\Controllers\DownloadController;
use App\Http\Controllers\LandingController;
use App\Http\Controllers\Payment\PaymentCallbackController;
use App\Models\QrToken;
use Illuminate\Support\Facades\Route;

Route::get('/', LandingController::class)->name('welcome');
Route::get('/download', [DownloadController::class, 'page'])->name('download');
Route::get('/download/android', [DownloadController::class, 'android'])->name('download.android');
Route::view('/privacy', 'public-page', ['page' => 'privacy'])->name('privacy.public');
Route::view('/support', 'public-page', ['page' => 'support'])->name('support.public');

Route::get('payment/callback/{payment:uuid}', PaymentCallbackController::class)->name('payment.callback');

Route::get('q/{token}', function (string $token) {
    $qr = QrToken::findActiveByCode($token);
    abort_unless($qr && $qr->station, 404, 'کد QR نامعتبر یا غیرفعال است.');

    return redirect()->route('app.charger', ['station' => $qr->station->code, 'connector' => $qr->connector_id, 'qr' => $qr->token]);
})->where('token', '[A-Za-z0-9]+')->name('qr.open');

Route::prefix('admin')->name('admin.')->group(base_path('routes/admin.php'));
Route::prefix('partner')->name('partner.')->group(base_path('routes/partner.php'));
Route::prefix('app')->name('app.')->group(base_path('routes/app.php'));
