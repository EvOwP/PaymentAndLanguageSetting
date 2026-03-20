<?php

namespace App\Services\PaymentGateway;

use App\Models\Payment;
use Illuminate\Http\Request;

class ManualDriver extends GatewayDriver
{
    public function process(Payment $payment, array $data)
    {
        return [
            'type' => 'message',
            'message' => 'Upload proof to process.'
        ];
    }

    public function handleWebhook(Request $request): array
    {
        // No webhooks for manual bank transfers usually
        return [];
    }
}
