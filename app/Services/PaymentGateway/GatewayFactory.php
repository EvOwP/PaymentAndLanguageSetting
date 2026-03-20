<?php

namespace App\Services\PaymentGateway;

use App\Models\PaymentGateway;

class GatewayFactory
{
    public static function make(PaymentGateway $gateway): GatewayDriver
    {
        if ($gateway->is_manual) {
            return new ManualDriver($gateway);
        }

        $name = strtolower($gateway->name);

        return match (true) {
            str_contains($name, 'stripe') => new StripeDriver($gateway),
            str_contains($name, 'paypal') => new PaypalDriver($gateway),
            str_contains($name, 'razorpay') => new RazorpayDriver($gateway),
            str_contains($name, 'paystack') => new PaystackDriver($gateway),
            str_contains($name, 'flutterwave') => new FlutterwaveDriver($gateway),
            str_contains($name, 'mollie') => new MollieDriver($gateway),
            str_contains($name, 'mercadopago') => new MercadoPagoDriver($gateway),
            str_contains($name, 'midtrans') => new MidtransDriver($gateway),
            str_contains($name, 'xendit') => new XenditDriver($gateway),
            str_contains($name, 'paytm') => new PaytmDriver($gateway),
            str_contains($name, 'instamojo') => new InstamojoDriver($gateway),
            str_contains($name, 'authorize.net') => new AuthorizeNetDriver($gateway),
            str_contains($name, 'coinbase') => new CoinbaseDriver($gateway),
            str_contains($name, 'binance') => new BinanceDriver($gateway),
            str_contains($name, 'payu') => new PayuDriver($gateway),
            default => new ManualDriver($gateway),
        };
    }
}
