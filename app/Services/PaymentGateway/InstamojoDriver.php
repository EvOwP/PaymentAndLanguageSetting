<?php

namespace App\Services\PaymentGateway;

use App\Models\Payment;
use Illuminate\Http\Request;

class InstamojoDriver extends GatewayDriver
{
    public function process(Payment $payment, array $data)
    {
        return ['type' => 'redirect', 'url' => 'https://test.instamojo.com/@admin/' . $payment->uuid];
    }
    public function handleWebhook(Request $request): array
    {
        $payload = $request->all(); // Usually via POST redirection
        $status = 'pending';
        if (($payload['status'] ?? '') === 'Credit') $status = 'paid';
        elseif (($payload['status'] ?? '') === 'Failed') $status = 'failed';
        return [
            'external_id' => $payload['payment_id'] ?? null,
            'local_uuid' => $payload['custom_fields']['order_uuid'] ?? null,
            'status' => $status,
            'fee' => $payload['fees'] ?? 0,
            'payload' => $payload
        ];
    }
}
