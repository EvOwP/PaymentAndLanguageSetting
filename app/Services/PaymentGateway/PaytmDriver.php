<?php

namespace App\Services\PaymentGateway;

use App\Models\Payment;
use Illuminate\Http\Request;

class PaytmDriver extends GatewayDriver
{
    public function process(Payment $payment, array $data)
    {
        return ['type' => 'view', 'view' => 'paytm_checkout'];
    }
    public function handleWebhook(Request $request): array
    {
        $payload = $request->all();
        $status = 'pending';
        if (($payload['STATUS'] ?? '') === 'TXN_SUCCESS') $status = 'paid';
        elseif (($payload['STATUS'] ?? '') === 'TXN_FAILURE') $status = 'failed';
        return [
            'external_id' => $payload['TXNID'] ?? null,
            'local_uuid' => $payload['ORDERID'] ?? null,
            'status' => $status,
            'fee' => 0,
            'payload' => $payload
        ];
    }
}
