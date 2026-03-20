<?php

namespace App\Services\PaymentGateway;

use App\Models\Payment;
use Illuminate\Http\Request;

class MercadoPagoDriver extends GatewayDriver
{
    public function process(Payment $payment, array $data)
    {
        return ['type' => 'redirect', 'url' => 'https://www.mercadopago.com/checkout/v1/redirect?pref_id=' . $payment->uuid];
    }
    public function handleWebhook(Request $request): array
    {
        $payload = $request->all();
        $status = 'pending';
        if (($payload['status'] ?? '') === 'approved') $status = 'paid';
        elseif (($payload['status'] ?? '') === 'rejected') $status = 'failed';
        return [
            'external_id' => $payload['id'] ?? null,
            'local_uuid' => $payload['external_reference'] ?? null,
            'status' => $status,
            'fee' => 0,
            'payload' => $payload
        ];
    }
}
