<?php

namespace CallpayGatway\Callpay\Providers;

use CallpayGatway\Callpay\Contracts\Callpay as CallpayContract;
use CallpayGatway\Callpay\ObjectValues\CallpayToken;
use CallpayGatway\Callpay\Callpay;
use Botble\Base\Traits\LoadAndPublishDataTrait;
use Illuminate\Foundation\Application;
use Illuminate\Support\ServiceProvider;

class CallpayServiceProvider extends ServiceProvider
{
    use LoadAndPublishDataTrait;

    public const MODULE_NAME = 'callpay';

    public function register(): void
    {
        if (! is_plugin_active('payment')) {
            return;
        }

        if (
            ! is_plugin_active('ecommerce') &&
            ! is_plugin_active('job-board') &&
            ! is_plugin_active('real-estate') &&
            ! is_plugin_active('hotel')
        ) {
            return;
        }

        $this->app->singleton(
            CallpayContract::class,
            fn (Application $app) => new Callpay(
                new CallpayToken(
                    get_payment_setting('username', self::MODULE_NAME, ''),
                    get_payment_setting('password', self::MODULE_NAME, ''),
                    get_payment_setting('salt_key', self::MODULE_NAME, '')
                )
            )
        );
    }

    public function boot(): void
    {
        if (! is_plugin_active('payment')) {
            return;
        }

        if (
            ! is_plugin_active('ecommerce') &&
            ! is_plugin_active('job-board') &&
            ! is_plugin_active('real-estate') &&
            ! is_plugin_active('hotel')
        ) {
            return;
        }

        $this->setNamespace('plugins/callpay')
            ->loadAndPublishTranslations()
            ->loadAndPublishViews()
            ->publishAssets()
            ->loadRoutes();

        $this->app->booted(function () {
            $this->app->register(HookServiceProvider::class);
        });
    }
}
