<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use App\Models\PaymentLog;
use App\Models\PaymentTransaction;
use App\Models\PaymentGateway;
use App\Services\PaymentGateway\GatewayFactory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class WebhookController extends Controller
{
    /**
     * Handle incoming webhooks for ANY gateway using its respective driver 
     */
    public function handle(Request $request, $gatewayName)
    {
        // 1. Find Gateway and its driver
        $gatewayModel = PaymentGateway::where('name', 'like', $gatewayName)->first();
        if (!$gatewayModel) {
            Log::error("Webhook error: Unknown gateway: $gatewayName");
            return response()->json(['error' => 'Unsupported gateway'], 400);
        }

        $driver = GatewayFactory::make($gatewayModel);

        // 2. Process through driver's normalization logic
        $data = $driver->handleWebhook($request);

        if (empty($data)) {
            Log::info("Webhook for $gatewayName handled but no payment mutation required.");
            return response()->json(['message' => 'Processed'], 200);
        }

        $localUuid = $data['local_uuid'] ?? null;
        $externalId = $data['external_id'] ?? null;
        $status = $data['status'] ?? 'pending';
        $payload = $data['payload'] ?? $request->all();

        // 3. Update core records
        $payment = Payment::where('uuid', $localUuid)->first();
        if (!$payment) return response()->json(['error' => 'Payment not found'], 404);

        $payment->update([
            'status' => $status,
            'webhook_payload' => $payload,
            'fee' => $data['fee'] ?? $payment->fee ?? 0,
            'net_amount' => $payment->amount - ($data['fee'] ?? 0)
        ]);

        PaymentTransaction::create([
            'payment_id' => $payment->id,
            'external_id' => $externalId,
            'status' => $status,
            'amount' => $payment->amount,
            'currency' => $payment->currency,
            'payload' => $payload
        ]);

        PaymentLog::create([
            'payment_id' => $payment->id,
            'event_type' => 'webhook_' . $status,
            'payload' => $payload,
            'ip_address' => $request->ip()
        ]);

        return response()->json(['message' => 'Webhook Handled successfully'], 200);
    }
}
