<?php

namespace App\Services\PaymentGateway;

use App\Models\Payment;
use Illuminate\Http\Request;

class MollieDriver extends GatewayDriver
{
    public function process(Payment $payment, array $data)
    {
        return ['type' => 'redirect', 'url' => 'https://www.mollie.com/checkout/' . $payment->uuid];
    }
    public function handleWebhook(Request $request): array
    {
        $payload = $request->all(); // Mollie usually sends an ID, you then call API to get full payload
        return [
            'external_id' => $payload['id'] ?? null,
            'local_uuid' => $payload['metadata']['order_uuid'] ?? null,
            'status' => ($payload['status'] === 'paid') ? 'paid' : 'failed',
            'fee' => 0, // Mollie typically bills separately or at settlement
            'payload' => $payload
        ];
    }
}
