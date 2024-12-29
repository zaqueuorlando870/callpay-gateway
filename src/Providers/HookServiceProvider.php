<?php

namespace CallpayGatway\Callpay\Providers;

use Botble\Hotel\Models\Booking;
use Botble\Payment\Facades\PaymentMethods;
use Botble\Payment\Models\Payment;
use CallpayGatway\Callpay\Contracts\Callpay as CallpayServiceContract;
use CallpayGatway\Callpay\Services\CallpayPaymentService;
use Botble\Ecommerce\Models\Currency as CurrencyEcommerce;
use Botble\JobBoard\Models\Currency as CurrencyJobBoard;
use Botble\RealEstate\Models\Currency as CurrencyRealEstate;
use Botble\Hotel\Models\Currency as CurrencyHotel;
use Botble\Payment\Enums\PaymentMethodEnum;
use Botble\Payment\Supports\PaymentHelper;
use Collective\Html\HtmlFacade as Html;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

class HookServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        add_filter(PAYMENT_METHODS_SETTINGS_PAGE, function (string|null $settings) {
            $name = 'Callpay Gateway';
            $description = trans('plugins/callpay::callpay.description');
            $link = 'https://www.callpay.com/';
            $image = asset('vendor/core/plugins/callpay/images/callpay.png');
            $moduleName = CallpayServiceProvider::MODULE_NAME;
            $status = (bool)get_payment_setting('status', $moduleName);

            return $settings . view(
                'plugins/callpay::settings',
                compact('name', 'description', 'link', 'image', 'moduleName', 'status')
            )->render();
        }, 999);

        add_filter(BASE_FILTER_ENUM_ARRAY, function (array $values, string $class): array {
            if ($class === PaymentMethodEnum::class) {
                $values['CALLPAY'] = CallpayServiceProvider::MODULE_NAME;
            }

            return $values;
        }, 999, 2);

        add_filter(BASE_FILTER_ENUM_LABEL, function ($value, $class): string {
            if ($class === PaymentMethodEnum::class && $value === CallpayServiceProvider::MODULE_NAME) {
                $value = 'Callpay';
            }

            return $value;
        }, 999, 2);

        add_filter(BASE_FILTER_ENUM_HTML, function (string $value, string $class): string {
            if ($class === PaymentMethodEnum::class && $value === CallpayServiceProvider::MODULE_NAME) {
                $value = Html::tag(
                    'span',
                    PaymentMethodEnum::getLabel($value),
                    ['class' => 'label-success status-label']
                )->toHtml();
            }

            return $value;
        }, 999, 2);

        add_filter(PAYMENT_FILTER_ADDITIONAL_PAYMENT_METHODS, function (string|null $html, array $data): string|null {
            if (get_payment_setting('status', CallpayServiceProvider::MODULE_NAME)) {
                $supportedCurrencies = $this->app->make(CallpayPaymentService::class)->getSupportedCurrencies();
                $currencies = get_all_currencies()
                    ->filter(fn ($currency) => in_array($currency->title, $supportedCurrencies));

                PaymentMethods::method(CallpayServiceProvider::MODULE_NAME, [
                    'html' => view(
                        'plugins/callpay::method',
                        array_merge($data, [
                            'moduleName' => CallpayServiceProvider::MODULE_NAME,
                            'supportedCurrencies' => $supportedCurrencies,
                            'currencies' => $currencies,
                        ]),
                    )->render(),
                ]);
            }

            return $html;
        }, 999, 2);

        add_filter(PAYMENT_FILTER_GET_SERVICE_CLASS, function (string|null $data, string $value): string|null {
            if ($value === CallpayServiceProvider::MODULE_NAME) {
                $data = CallpayPaymentService::class;
            }

            return $data;
        }, 20, 2);

        add_filter(PAYMENT_FILTER_AFTER_POST_CHECKOUT, callback: function (array $data, Request $request): array {
            if ($data['type'] !== CallpayServiceProvider::MODULE_NAME) {
                return $data;
            }

            $currentCurrency = get_application_currency();

            $paymentData = apply_filters(PAYMENT_FILTER_PAYMENT_DATA, [], $request);

            if (strtoupper($currentCurrency->title) !== 'AOA') {
                $currency = match (true) {
                    is_plugin_active('ecommerce') => CurrencyEcommerce::class,
                    is_plugin_active('job-board') => CurrencyJobBoard::class,
                    is_plugin_active('real-estate') => CurrencyRealEstate::class,
                    is_plugin_active('hotel') => CurrencyHotel::class,
                    default => null,
                };

                $supportedCurrency = $currency::query()->where('title', 'AOA')->first();

                if ($supportedCurrency) {
                    $paymentData['currency'] = strtoupper($supportedCurrency->title);
                    if ($currentCurrency->is_default) {
                        $paymentData['amount'] = $paymentData['amount'] * $supportedCurrency->exchange_rate;
                    } else {
                        $paymentData['amount'] = format_price(
                            $paymentData['amount'] / $currentCurrency->exchange_rate,
                            $currentCurrency,
                            true
                        );
                    }
                }
            }

            $supportedCurrencies = $this->app->make(CallpayPaymentService::class)->getSupportedCurrencies();

            if (! in_array($paymentData['currency'], $supportedCurrencies)) {
                $data['error'] = true;
                $data['message'] = __(":name doesn't support :currency. List of currencies supported by :name: :currencies.", ['name' => 'Callpay', 'currency' => $data['currency'], 'currencies' => implode(', ', $supportedCurrencies)]);

                return $data;
            }

            $orderIds = $paymentData['order_id'];

            try {
                $callpay = $this->app->make(CallpayServiceContract::class);
                $chargeId = $callpay->transactionId();

                $returnUrl = PaymentHelper::getRedirectURL($paymentData['checkout_token']);

                if (is_plugin_active('job-board')) {
                    $returnUrl = $returnUrl . '?charge_id=' . $chargeId;
                }

                if (is_plugin_active('hotel')) {
                    $payment = Payment::query()->select('order_id')->where('charge_id', $chargeId)->first();
                    if ($payment) {
                        $order = Booking::query()->find($payment->order_id);

                        if ($order) {
                            $returnUrl = PaymentHelper::getRedirectURL($order->transaction_id);
                        }
                    }
                }
                
                // dd($paymentData);

                $callpay->renderCheckoutForm([
                    'return_url' => $returnUrl,
                    'notify_url' => route('payment.callpay.webhook'),
                    'success_url' => route('payment.callpay.webhook'),
                    'name_first' => Str::of($paymentData['address']['name'])->before(' ')->toString(),
                    'name_last' => Str::of($paymentData['address']['name'])->after(' ')->toString(),
                    'email_address' => $paymentData['address']['email'],
                    'cell_number' => $paymentData['address']['phone'],
                    'm_payment_id' => $chargeId,
                    'amount' => $callpay->formatAmount($paymentData['amount']),
                    'item_name' => $paymentData['description'],
                    'custom_str1' => $orderIds[0],
                    'custom_str2' => $paymentData['customer_id'],
                    'custom_str3' => addslashes($paymentData['customer_type']),
                    'custom_str4' => $paymentData['checkout_token'],
                    'custom_str5' => $paymentData['currency'],
                ]);
            } catch (Exception $exception) {
                $data['error'] = true;
                $data['message'] = json_encode($exception->getMessage());
            }

            return $data;
        }, priority: 999, arguments: 2);
    }
}
