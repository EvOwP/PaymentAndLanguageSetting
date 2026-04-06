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

    abstract public function handleWebhook(Request $request): array;

    /**
     * Finalize payment after redirect (e.g. Capture PayPal order)
     */
    public function finalize(Payment $payment, array $data): array
    {
        return ['status' => $payment->status];
    }

    /**
     * Trigger a refund for a payment
     */
    public function refund(Payment $payment, $amount = null, $reason = null): array
    {
        throw new \Exception("Refund not implemented for this gateway.");
    }

    /**
     * Check status of a payment from the gateway API (Fallback/Cron)
     */
    public function checkStatus(Payment $payment): array
    {
        return ['status' => $payment->status];
    }

    /**
     * Helper to get single credential easily
     */
    protected function getCred($key)
    {
        return $this->gateway->credentials[$key] ?? null;
    }
}
