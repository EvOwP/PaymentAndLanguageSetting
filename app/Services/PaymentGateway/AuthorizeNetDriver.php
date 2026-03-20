<?php

namespace App\Services\PaymentGateway;

use App\Models\Payment;
use Illuminate\Http\Request;

class AuthorizeNetDriver extends GatewayDriver
{
    public function process(Payment $payment, array $data)
    {
        return ['type' => 'view', 'view' => 'authorizenet_checkout'];
    }
    public function handleWebhook(Request $request): array
    {
        $payload = $request->all();
        $code = $payload['payload']['transactionResponse']['responseCode'] ?? '';
        $status = 'pending';
        if ($code == '1') $status = 'paid';
        elseif ($code == '2' || $code == '3') $status = 'failed';
        return [
            'external_id' => $payload['payload']['id'] ?? null,
            'local_uuid' => $payload['payload']['merchantReferenceId'] ?? null,
            'status' => $status,
            'fee' => 0,
            'payload' => $payload
        ];
    }
}
