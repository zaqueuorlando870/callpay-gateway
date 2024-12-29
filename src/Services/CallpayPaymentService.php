<?php

namespace CallpayGatway\Callpay\Services;

class CallpayPaymentService extends PaymentServiceAbstract
{
    public function isSupportRefundOnline(): bool
    {
        return true;
    }

    public function getSupportedCurrencies(): array
    {
        return [
            'AOA',
        ];
    }

    public function refund(string $chargeId, float $amount): array
    {
        return [];
    }
}
