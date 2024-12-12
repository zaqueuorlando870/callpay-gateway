<?php
namespace Botble\CallpayGateway;

use Botble\Payment\Base\Gateways\PaymentAbstract;
use Botble\Payment\Models\Transaction;
use Illuminate\Http\Request;
use Botble\Base\Interfaces\PluginInterface;
use Schema;

class Plugin implements PluginInterface
{
    public static function activate()
    {
        Schema::create('callpay_transactions', function ($table) {
            $table->id();
            $table->string('payment_key');
            $table->string('status');
            $table->timestamps();
        });
    }

    public static function deactivate()
    {
        // Handle deactivation
    }

    public static function remove()
    {
        Schema::dropIfExists('callpay_transactions');
    }
}