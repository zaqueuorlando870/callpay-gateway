<?php

use Botble\CallpayGateway\Http\Controllers\CallpayController;

Route::group(['prefix' => 'callpay', 'middleware' => ['web', 'core']], function () {
    Route::get('/initiate-payment', [CallpayController::class, 'initiatePayment'])->name('callpay.initiate');
});