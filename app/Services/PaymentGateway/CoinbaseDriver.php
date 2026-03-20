<?php

namespace App\Services\PaymentGateway;

use App\Models\Payment;
use Illuminate\Http\Request;

class CoinbaseDriver extends GatewayDriver
{
    public function process(Payment $payment, array $data)
    {
        return ['type' => 'redirect', 'url' => 'https://commerce.coinbase.com/charges/' . $payment->uuid];
    }
    public function handleWebhook(Request $request): array
    {
        $payload = $request->all();
        $event = $payload['event']['type'] ?? '';
        $status = 'pending';
        if ($event === 'charge:confirmed') $status = 'paid';
        elseif ($event === 'charge:failed') $status = 'failed';
        return [
            'external_id' => $payload['event']['data']['id'] ?? null,
            'local_uuid' => $payload['event']['data']['metadata']['order_uuid'] ?? null,
            'status' => $status,
            'fee' => 0,
            'payload' => $payload
        ];
    }
}
