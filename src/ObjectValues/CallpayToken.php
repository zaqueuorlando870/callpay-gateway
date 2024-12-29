<?php

namespace CallpayGatway\Callpay\ObjectValues;

use CallpayGatway\Callpay\Exceptions\MerchantConfigurationException;

class CallpayToken
{
    public function __construct(
        protected string $username,
        protected string $password,
        protected string $saltKey,
    ) {
        if (empty($this->username) || empty($this->password) || empty($this->saltKey)) {
            throw new MerchantConfigurationException();
        }
    }

    public function getUsername(): string
    {
        return $this->username;
    }

    public function getPassword(): string
    {
        return $this->password;
    }

    public function getSaltKey(): string
    {
        return $this->saltKey;
    }
}
