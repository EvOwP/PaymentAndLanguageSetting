<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

use App\Models\Language;
use App\Models\PaymentGateway;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();

        User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);

        \App\Models\Setting::create([
            'key' => 'show_language_options',
            'value' => 'true'
        ]);

        Language::create([
            'code' => 'en',
            'name' => 'English',
            'is_rtl' => false,
            'is_default' => true,
            'is_active' => true,
            'show_in_navbar' => true,
        ]);

        $gateways = [
            [
                'name' => 'Bank Transfer (Manual)',
                'is_manual' => true,
                'status' => true,
                'currency' => 'USD',
                'instructions' => "Please transfer to Bank XYZ\nAccount Name: Admin\nAccount Number: 1234567890\nUpload your receipt after payment.",
                'credentials' => null,
            ],
            [
                'name' => 'Stripe',
                'is_manual' => false,
                'status' => true,
                'currency' => 'USD',
                'credentials' => [
                    'STRIPE_PUBLIC_KEY' => 'pk_test_xxxxxx',
                    'STRIPE_SECRET_KEY' => 'sk_test_xxxxxx',
                    'STRIPE_WEBHOOK_SECRET' => 'whsec_xxxxxx',
                ],
            ],
            [
                'name' => 'PayPal',
                'is_manual' => false,
                'status' => true,
                'currency' => 'USD',
                'credentials' => [
                    'PAYPAL_CLIENT_ID' => 'client_id_xxxxxx',
                    'PAYPAL_CLIENT_SECRET' => 'secret_xxxxxx',
                    'PAYPAL_MODE' => 'sandbox', // sandbox or live
                ],
            ],
            [
                'name' => 'Razorpay',
                'is_manual' => false,
                'status' => true,
                'currency' => 'INR',
                'credentials' => [
                    'RAZORPAY_KEY_ID' => 'rzp_test_xxxxxx',
                    'RAZORPAY_KEY_SECRET' => 'secret_xxxxxx',
                ],
            ],
            [
                'name' => 'Paystack',
                'is_manual' => false,
                'status' => true,
                'currency' => 'NGN',
                'credentials' => [
                    'PAYSTACK_PUBLIC_KEY' => 'pk_test_xxxxxx',
                    'PAYSTACK_SECRET_KEY' => 'sk_test_xxxxxx',
                ],
            ],
            [
                'name' => 'Flutterwave',
                'is_manual' => false,
                'status' => true,
                'currency' => 'NGN',
                'credentials' => [
                    'FLW_PUBLIC_KEY' => 'FLWPUBK_TEST-xxxxxx',
                    'FLW_SECRET_KEY' => 'FLWSECK_TEST-xxxxxx',
                    'FLW_SECRET_HASH' => 'hash_xxxxxx',
                ],
            ],
            [
                'name' => 'Mollie',
                'is_manual' => false,
                'status' => true,
                'currency' => 'EUR',
                'credentials' => [
                    'MOLLIE_API_KEY' => 'test_xxxxxx',
                ],
            ],
            [
                'name' => 'MercadoPago',
                'is_manual' => false,
                'status' => true,
                'currency' => 'BRL',
                'credentials' => [
                    'MERCADOPAGO_PUBLIC_KEY' => 'xxxxxx',
                    'MERCADOPAGO_ACCESS_TOKEN' => 'xxxxxx',
                ],
            ],
            [
                'name' => 'Midtrans',
                'is_manual' => false,
                'status' => true,
                'currency' => 'IDR',
                'credentials' => [
                    'MIDTRANS_CLIENT_KEY' => 'SB-Mid-client-xxxxxx',
                    'MIDTRANS_SERVER_KEY' => 'SB-Mid-server-xxxxxx',
                    'MIDTRANS_IS_PRODUCTION' => 'false',
                ],
            ],
            [
                'name' => 'Xendit',
                'is_manual' => false,
                'status' => true,
                'currency' => 'IDR',
                'credentials' => [
                    'XENDIT_SECRET_KEY' => 'xnd_development_xxxxxx',
                    'XENDIT_PUBLIC_KEY' => 'xnd_public_xxxxxx',
                ],
            ],
            [
                'name' => 'PayTM',
                'is_manual' => false,
                'status' => true,
                'currency' => 'INR',
                'credentials' => [
                    'PAYTM_ENVIRONMENT' => 'local',
                    'PAYTM_MERCHANT_ID' => 'xxxxxx',
                    'PAYTM_MERCHANT_KEY' => 'xxxxxx',
                    'PAYTM_MERCHANT_WEBSITE' => 'WEBSTAGING',
                    'PAYTM_CHANNEL' => 'WEB',
                    'PAYTM_INDUSTRY_TYPE' => 'Retail',
                ],
            ],
            [
                'name' => 'Instamojo',
                'is_manual' => false,
                'status' => true,
                'currency' => 'INR',
                'credentials' => [
                    'INSTAMOJO_API_KEY' => 'test_xxxxxx',
                    'INSTAMOJO_AUTH_TOKEN' => 'test_xxxxxx',
                    'INSTAMOJO_SALT' => 'test_xxxxxx',
                ],
            ],
            [
                'name' => 'Authorize.Net',
                'is_manual' => false,
                'status' => true,
                'currency' => 'USD',
                'credentials' => [
                    'AUTHORIZENET_API_LOGIN_ID' => 'xxxxxx',
                    'AUTHORIZENET_TRANSACTION_KEY' => 'xxxxxx',
                ],
            ],
            [
                'name' => 'Coinbase Commerce',
                'is_manual' => false,
                'status' => true,
                'currency' => 'USD',
                'credentials' => [
                    'COINBASE_API_KEY' => 'xxxxxx',
                    'COINBASE_WEBHOOK_SECRET' => 'xxxxxx',
                ],
            ],
            [
                'name' => 'Binance Pay',
                'is_manual' => false,
                'status' => true,
                'currency' => 'USDT',
                'credentials' => [
                    'BINANCE_API_KEY' => 'xxxxxx',
                    'BINANCE_SECRET_KEY' => 'xxxxxx',
                ],
            ],
            [
                'name' => 'PayU Money',
                'is_manual' => false,
                'status' => true,
                'currency' => 'INR',
                'credentials' => [
                    'PAYU_MERCHANT_KEY' => 'xxxxxx',
                    'PAYU_MERCHANT_SALT' => 'xxxxxx',
                    'PAYU_MODE' => 'sandbox',
                ],
            ],
        ];

        foreach ($gateways as $gateway) {
            PaymentGateway::create($gateway);
        }
    }
}
