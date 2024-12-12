<?php 

namespace Botble\CallpayGateway\Providers;

use Illuminate\Support\ServiceProvider;

class CallpayGatewayServiceProvider extends ServiceProvider
{
    public function boot()
    {
        $this->loadRoutesFrom(__DIR__.'/../routes/web.php');
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'callpay');
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
    }

    public function register()
    {
        // Register dependencies
    }
}
