<?php 

namespace Botble\CallpayGateway\Http\Controllers;

use Illuminate\Http\Request;
use Botble\Payment\Base\Gateways\PaymentAbstract;
use Botble\Payment\Models\Transaction; 
use Illuminate\Support\Facades\Http;


class CallpayController
{
    public function initiatePayment(Request $request)
    {
        $paymentKey = $this->generatePaymentKey($request);
        return view('callpay::payment-form', compact('paymentKey'));
    }

    private function generatePaymentKey(Request $request): string
    {
        $amount = (int) $request->input('amount');
        $successUrl = route('payment.success');
        $errorUrl = route('payment.error');

        $response = Http::post('https://services.callpay.com/api/payment-key', [
            'amount' => $amount,
            'redirect_success_url' => $successUrl,
            'redirect_error_url' => $errorUrl,
        ]);

        return $response->json('payment_key');
    }
}