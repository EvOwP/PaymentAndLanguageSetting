<?php

namespace App\Services\PaymentGateway;

use App\Models\Payment;
use Illuminate\Http\Request;

class PayuDriver extends GatewayDriver
{
    public function process(Payment $payment, array $data)
    {
        return ['type' => 'redirect', 'url' => 'https://secure.payu.in/_payment/' . $payment->uuid];
    }
    public function handleWebhook(Request $request): array
    {
        $payload = $request->all(); // PayU uses POST response
        $status = 'pending';
        if (($payload['status'] ?? '') === 'success') $status = 'paid';
        elseif (($payload['status'] ?? '') === 'failure') $status = 'failed';
        return [
            'external_id' => $payload['mihpayid'] ?? null,
            'local_uuid' => $payload['txnid'] ?? null,
            'status' => $status,
            'fee' => 0,
            'payload' => $payload
        ];
    }
}
