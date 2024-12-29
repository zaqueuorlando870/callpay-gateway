<?php

namespace CallpayGatway\Callpay\Exceptions;

use Exception;

class MerchantConfigurationException extends Exception
{
    public function __construct(string $message = 'Callpay username, Password or Salt Key is not set.', int $code = 400)
    {
        parent::__construct($message, $code);
    }
}
