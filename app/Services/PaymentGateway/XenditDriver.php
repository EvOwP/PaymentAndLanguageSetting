<?php

namespace App\Services\PaymentGateway;

use App\Models\Payment;
use Illuminate\Http\Request;

class XenditDriver extends GatewayDriver
{
    public function process(Payment $payment, array $data)
    {
        return ['type' => 'redirect', 'url' => 'https://checkout.xendit.co/web/' . $payment->uuid];
    }
    public function handleWebhook(Request $request): array
    {
        $payload = $request->all();
        return [
            'external_id' => $payload['id'] ?? null,
            'local_uuid' => $payload['external_id'] ?? null,
            'status' => ($payload['status'] === 'PAID') ? 'paid' : 'failed',
            'fee' => $payload['fees_paid_amount'] ?? 0,
            'payload' => $payload
        ];
    }
}
