<?php

namespace CallpayGatway\Callpay;

use CallpayGatway\Callpay\Contracts\Callpay as CallpayContract;
use CallpayGatway\Callpay\Exceptions\InvalidEnvironmentException;
use CallpayGatway\Callpay\ObjectValues\CallpayToken;
use CallpayGatway\Callpay\Providers\CallpayServiceProvider;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class Callpay implements CallpayContract
{
    protected array $endpoint = [
        'live' => 'https://www.services.callpay.com',
        'sandbox' => 'https://www.services.callpay.com', 
    ];

    public function __construct(protected CallpayToken $callpayToken)
    {
    }

    public function renderCheckoutForm(array $data): void
    {
        try {
            $paymentKey = $this->generatePaymentKey(
                $this->callpayToken->getUsername(),
                $this->callpayToken->getPassword(),
                $data['amount'],
                $data['m_payment_id']
            ); 
            // Set payment key in request data 
            echo view('plugins/callpay::form', [
                'data' => $this->getDataForCheckoutForm($data),
                'action' => $paymentKey,
            ]); 
            exit();
        } catch (Exception $exception) {
            $this->setErrorMessageAndLogging($exception, 1);
        } 
    }
    
    
    private function generatePaymentKey(string $username, string $password, float $amount, string $merchantReference): string
    {
        // implementation of generatePaymentKey method
        $curl = curl_init();

        curl_setopt_array($curl, array(
        CURLOPT_URL => 'https://services.callpay.com/api/v1/payment-key',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => '',
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 0,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => 'POST',
        CURLOPT_POSTFIELDS => 'amount='.$amount.'&merchant_reference='.$merchantReference,
        CURLOPT_HTTPHEADER => array(
            'Authorization: Basic ' . base64_encode($username . ':' . $password),
        ),
        ));

        $response = curl_exec($curl);

        if (curl_errno($curl)) {
            $error = curl_error($curl);
            // Handle the error
            return $error;
        }
        
        curl_close($curl);  
        $response = json_decode($response, true);

        if (!isset($response['url'])) {
            // Handle the error
            return "Invalid response from API";  
        } 
        
        $paymentUrl = $response['url'];

        return $paymentUrl; 
    }

    public function transactionId(): string
    {
        return Str::random(10);
    }

    public function formatAmount(mixed $amount): float
    {
        return (float) number_format((float)sprintf('%.2f', $amount), 2, '.', '');
    }

    public function validIpAddress(): bool
    {
        if (app()->environment('local')) {
            return true;
        }

        $validHosts = [
            'www.services.callpay.com',
            'www.callpay.com',
        ];

        $validIps = [];

        foreach ($validHosts as $hostname) {
            $ips = gethostbynamel($hostname);

            if ($ips === false) {
                continue;
            }

            $validIps = array_merge($validIps, $ips);
        }

        $referrerIp = request()->server('REMOTE_ADDR');

        if (! in_array($referrerIp, array_unique($validIps), true)) {
            return false;
        }

        return true;
    }

    public function validPaymentData(float $amount, array $data): bool
    {
        return ! (abs($amount - (float) $data['amount_gross']) > 0.01);
    }

    public function validSignature(array $data): bool
    {
        $signature = $this->generateSignature($data, $this->callpayToken->getMerchantPassphrase());

        return $data['signature'] === $signature;
    }

    public function validServerConfirmation(array $data): bool
    {
        $response = $this->request()->post('/query/validate', $data)->body();

        return $response === 'VALID';
    }

    protected function request(): PendingRequest
    {
        return Http::baseUrl($this->getEndpointUrl());
    }

    protected function getEndpointUrl(string $uri = null): string
    {
        $environment = get_payment_setting('environment', CallpayServiceProvider::MODULE_NAME);

        if (! isset($this->endpoint[$environment])) {
            throw new InvalidEnvironmentException();
        }

        return $this->endpoint[$environment] . $uri;
    }

    protected function generateSignature($data, $passPhrase = null): string
    {
        $pfOutput = '';

        foreach ($data as $key => $val) {
            if ($key === 'signature') {
                continue;
            }

            if ($val !== '') {
                $pfOutput .= $key . '=' . urlencode(trim($val)) . '&';
            }
        }

        $getString = substr($pfOutput, 0, -1);

        if ($passPhrase !== null) {
            $getString .= '&passphrase=' . urlencode(trim($passPhrase));
        }

        return md5($getString);
    }

    protected function getDataForCheckoutForm(array $data): array
    {
        if (isset($data['cell_number']) && ! $this->validateCellNumber($data['cell_number'])) {
            unset($data['cell_number']);
        }

        $data = array_merge([
            'username' => $this->callpayToken->getUsername(),
            'password' => $this->callpayToken->getPassword(),
            'salt_key' => $this->callpayToken->getSaltKey(),
        ], $data);

        // $data['signature'] = $this->generateSignature($data, passPhrase: $this->callpayToken->getMerchantPassphrase());

        return $data;
    }

    protected function validateCellNumber(string $cellNumber): bool
    {
        if (! str_starts_with($cellNumber, '08')) {
            return false;
        }

        if (strlen($cellNumber) !== 10) {
            return false;
        }

        return true;
    }
}
