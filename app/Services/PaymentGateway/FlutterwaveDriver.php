<?php

namespace App\Services\PaymentGateway;

use App\Models\Payment;
use Illuminate\Http\Request;

class FlutterwaveDriver extends GatewayDriver
{
    public function process(Payment $payment, array $data)
    {
        return ['type' => 'redirect', 'url' => 'https://checkout.flutterwave.com/' . $payment->uuid];
    }
    public function handleWebhook(Request $request): array
    {
        $payload = $request->all();
        return [
            'external_id' => $payload['data']['id'] ?? null,
            'local_uuid' => $payload['data']['tx_ref'] ?? null,
            'status' => ($payload['data']['status'] === 'successful') ? 'paid' : 'failed',
            'fee' => $payload['data']['app_fee'] ?? 0,
            'payload' => $payload
        ];
    }
}
