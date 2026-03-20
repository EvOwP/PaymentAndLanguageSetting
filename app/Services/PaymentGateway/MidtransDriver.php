<?php

namespace App\Services\PaymentGateway;

use App\Models\Payment;
use Illuminate\Http\Request;

class MidtransDriver extends GatewayDriver
{
    public function process(Payment $payment, array $data)
    {
        return ['type' => 'redirect', 'url' => 'https://app.sandbox.midtrans.com/snap/v2/vtweb/' . $payment->uuid];
    }
    public function handleWebhook(Request $request): array
    {
        $payload = $request->all();
        $status = 'pending';
        if ($payload['transaction_status'] === 'settlement' || $payload['transaction_status'] === 'capture') $status = 'paid';
        elseif ($payload['transaction_status'] === 'cancel' || $payload['transaction_status'] === 'deny') $status = 'failed';
        return [
            'external_id' => $payload['transaction_id'] ?? null,
            'local_uuid' => $payload['order_id'] ?? null,
            'status' => $status,
            'fee' => 0,
            'payload' => $payload
        ];
    }
}
