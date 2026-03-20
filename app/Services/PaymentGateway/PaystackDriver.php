<?php

namespace App\Services\PaymentGateway;

use App\Models\Payment;
use Illuminate\Http\Request;

class PaystackDriver extends GatewayDriver
{
    public function process(Payment $payment, array $data)
    {
        return ['type' => 'redirect', 'url' => 'https://checkout.paystack.com/' . $payment->uuid];
    }
    public function handleWebhook(Request $request): array
    {
        $payload = $request->all();
        return [
            'external_id' => $payload['data']['reference'] ?? null,
            'local_uuid' => $payload['data']['metadata']['order_uuid'] ?? null,
            'status' => ($payload['event'] === 'charge.success') ? 'paid' : 'failed',
            'fee' => ($payload['data']['fees'] ?? 0) / 100,
            'payload' => $payload
        ];
    }
}
