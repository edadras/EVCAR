<?php

use App\Http\Controllers\Payment\PaymentCallbackController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
})->name('welcome');

Route::get('payment/callback/{payment:uuid}', PaymentCallbackController::class)->name('payment.callback');

Route::prefix('admin')->name('admin.')->group(base_path('routes/admin.php'));
Route::prefix('partner')->name('partner.')->group(base_path('routes/partner.php'));
Route::prefix('app')->name('app.')->group(base_path('routes/app.php'));
