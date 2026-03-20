<?php

namespace App\Services\PaymentGateway;

use App\Models\Payment;
use Illuminate\Http\Request;

abstract class GatewayDriver
{
    protected $gateway;

    public function __construct($gateway)
    {
        $this->gateway = $gateway;
    }

    /**
     * Initial redirect or form generation for the gateway
     */
    abstract public function process(Payment $payment, array $data);

    /**
     * Handle incoming webhook raw request
     */
    abstract public function handleWebhook(Request $request): array;

    /**
     * Helper to get single credential easily
     */
    protected function getCred($key)
    {
        return $this->gateway->credentials[$key] ?? null;
    }
}
