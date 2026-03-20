<?php

namespace App\Services\PaymentGateway;

use App\Models\Payment;
use Illuminate\Http\Request;

class PaypalDriver extends GatewayDriver
{
    public function process(Payment $payment, array $data)
    {
        return [
            'type' => 'redirect',
            'url' => 'https://www.paypal.com/checkoutnow/' . $payment->uuid
        ];
    }

    public function handleWebhook(Request $request): array
    {
        $payload = $request->all();
        $event = $payload['event_type'] ?? '';

        $status = 'pending';
        if ($event === 'PAYMENT.CAPTURE.COMPLETED') $status = 'paid';
        elseif ($event === 'PAYMENT.CAPTURE.DENIED') $status = 'failed';

        return [
            'external_id' => $payload['resource']['id'] ?? null,
            'local_uuid' => $payload['resource']['custom_id'] ?? null,
            'status' => $status,
            'fee' => $payload['resource']['seller_receivable_breakdown']['paypal_fee']['value'] ?? 0,
            'payload' => $payload
        ];
    }
}
