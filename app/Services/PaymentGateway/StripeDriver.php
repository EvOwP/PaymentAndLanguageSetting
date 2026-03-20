<?php

namespace App\Services\PaymentGateway;

use App\Models\Payment;
use Illuminate\Http\Request;

class StripeDriver extends GatewayDriver
{
    public function process(Payment $payment, array $data)
    {
        // Stripe actual initialization logic (Stripe::setApiKey...)
        return [
            'type' => 'redirect',
            'url' => 'https://checkout.stripe.com/pay/' . $payment->uuid
        ];
    }

    public function handleWebhook(Request $request): array
    {
        $payload = $request->all();
        $type = $payload['type'] ?? '';

        $status = 'pending';
        if ($type === 'charge.succeeded') $status = 'paid';
        elseif ($type === 'charge.failed') $status = 'failed';
        elseif ($type === 'charge.refunded') $status = 'refunded';

        return [
            'external_id' => $payload['data']['object']['id'] ?? null,
            'local_uuid' => $payload['data']['object']['metadata']['order_uuid'] ?? null,
            'status' => $status,
            'fee' => ($payload['data']['object']['balance_transaction']['fee'] ?? 0) / 100,
            'payload' => $payload
        ];
    }
}
