@extends('layouts.master')

@section('content')
<form id="payment-form" action="checkout?confirm=1" style="margin-top: 50px">
    <div class="text-center">
        <button id="pay-button" class="btn btn-primary btn-sx" type="button" data-payment-key="{{ $paymentKey }}">Pay</button>
    </div>
</form>
<script src="https://code.jquery.com/jquery-1.12.4.min.js"></script>
<script src="https://services.callpay.com/ext/checkout/v3/checkout.js" id="og-checkout" data-origin="https://services.callpay.com"></script>
<script>
    $(function() {
        $('#pay-button').on('click', function() {
            $(this).hide();
            var paymentKey = $(this).data('payment-key');
            eftSec.checkout.init({
                paymentKey: paymentKey,
                paymentType: 'eft',
                onLoad: function() {
                    $('#pay-button').show();
                },
                cardOptions: {
                    rememberCard: false
                },
            });
        });
    });
</script>
@endsection