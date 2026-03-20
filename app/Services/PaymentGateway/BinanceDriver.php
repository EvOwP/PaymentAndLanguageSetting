<?php

namespace App\Services\PaymentGateway;

use App\Models\Payment;
use Illuminate\Http\Request;

class BinanceDriver extends GatewayDriver
{
    public function process(Payment $payment, array $data)
    {
        return ['type' => 'redirect', 'url' => 'https://pay.binance.com/checkout/' . $payment->uuid];
    }
    public function handleWebhook(Request $request): array
    {
        $payload = $request->all();
        $status = 'pending';
        if (($payload['bizStatus'] ?? '') === 'PAY_SUCCESS') $status = 'paid';
        elseif (($payload['bizStatus'] ?? '') === 'PAY_CLOSED') $status = 'failed';
        return [
            'external_id' => $payload['bizId'] ?? null,
            'local_uuid' => $payload['merchantTradeNo'] ?? null,
            'status' => $status,
            'fee' => 0,
            'payload' => $payload
        ];
    }
}
