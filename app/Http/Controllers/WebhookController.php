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
        // 1. Initialize Log Context for better traceability
        Log::withContext([
            'gateway' => $gatewayName,
            'ip' => $request->ip(),
            'request_id' => $request->header('X-Request-ID') ?? uniqid()
        ]);

        Log::info("Incoming webhook received");

        // 1. Find Gateway and its driver
        $gatewayModel = PaymentGateway::whereRaw('LOWER(name) = ?', [strtolower($gatewayName)])->first();
        if (!$gatewayModel) {
            Log::error("Webhook denied: Unknown gateway configuration", ['requested_gateway' => $gatewayName]);
            return response()->json(['error' => 'Unsupported gateway'], 400);
        }

        $driver = GatewayFactory::make($gatewayModel);

        // 2. Process through driver's normalization logic
        try {
            $data = $driver->handleWebhook($request);
        } catch (\Exception $e) {
            Log::critical("Driver crashed during webhook processing", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json(['error' => 'Internal driver error'], 500);
        }

        if (empty($data)) {
            Log::info("Webhook ignored by driver: No actionable data found");
            return response()->json(['message' => 'Processed'], 200);
        }

        $localUuid = $data['local_uuid'] ?? null;
        $eventId = $data['event_id'] ?? $request->input('id');
        $eventType = $data['event_type'] ?? $request->input('type');
        $payload = $data['payload'] ?? $request->all();

        Log::shareContext(['event_id' => $eventId, 'event_type' => $eventType, 'payment_uuid' => $localUuid]);

        // Find the payment
        $payment = Payment::where('uuid', $localUuid)->first();
        
        if (!$payment) {
            Log::warning("Payment reconciliation failed: UUID not found in database", [
                'uuid' => $localUuid,
            ]);
            return response()->json(['error' => 'Payment not found'], 404);
        }

        Log::shareContext(['internal_payment_id' => $payment->id]);

        // 3. Create initial PaymentLog entry (unprocessed) to support retries if processing fails
        $paymentLog = PaymentLog::firstOrCreate(
            [
                'event_id' => $eventId,
                'payment_id' => $payment->id,
            ],
            [
                'event_type' => $eventType ?? 'webhook_received',
                'payload' => $payload,
                'ip_address' => $request->ip(),
                'is_verified' => $data['is_verified'] ?? false,
                'signature' => $data['signature'] ?? null,
                'processed' => false,
                'retry_count' => 0,
            ]
        );

        // If already processed, return success
        if ($paymentLog->processed) {
            Log::info("Event already processed: Skipping");
            return response()->json(['message' => 'Webhook already handled'], 200);
        }

        // 4. Use Reconciliation Service to process the update
        try {
            $reconciliationService = app(\App\Services\PaymentReconciliationService::class);
            $processed = $reconciliationService->reconcile(
                $payment,
                $data,
                $eventId,
                $eventType,
                $payload,
                $request->ip(),
                $data['signature'] ?? null,
                $data['is_verified'] ?? false
            );

            if ($processed) {
                Log::info("Webhook handled successfully - Response sent to gateway");
                return response()->json(['message' => 'Webhook Handled successfully'], 200);
            }
        } catch (\Exception $e) {
            Log::error("Reconciliation failed", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            // We return 500 so the gateway might retry, 
            // but we also have it in our payment_logs for manual/auto retry.
            return response()->json(['error' => 'Processing failed'], 500);
        }

        return response()->json(['error' => 'Processing failed'], 500);
    }
}
