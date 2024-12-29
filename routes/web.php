<?php

use CallpayGatway\Callpay\Http\Controllers\CallpayController;
use Illuminate\Support\Facades\Route;

Route::middleware(['core'])->prefix('payment/callpay')->name('payment.callpay.')->group(function () {
    Route::post('webhook', [CallpayController::class, 'webhook'])->name('webhook');
});
