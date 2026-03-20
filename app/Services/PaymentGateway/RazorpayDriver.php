<?php

namespace App\Services\PaymentGateway;

use App\Models\Payment;
use Illuminate\Http\Request;

class RazorpayDriver extends GatewayDriver
{
    public function process(Payment $payment, array $data)
    {
        return [
            'type' => 'view',
            'view' => 'razorpay_checkout',
            'order_id' => $payment->uuid
        ];
    }

    public function handleWebhook(Request $request): array
    {
        $payload = $request->all();
        $event = $payload['event'] ?? '';

        $status = 'pending';
        if ($event === 'order.paid' || $event === 'payment.captured') $status = 'paid';
        elseif ($event === 'payment.failed') $status = 'failed';

        return [
            'external_id' => $payload['payload']['payment']['entity']['id'] ?? null,
            'local_uuid' => $payload['payload']['order']['entity']['notes']['order_uuid'] ?? null,
            'status' => $status,
            'fee' => ($payload['payload']['payment']['entity']['fee'] ?? 0) / 100,
            'payload' => $payload
        ];
    }
}
