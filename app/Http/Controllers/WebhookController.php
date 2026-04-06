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
        $externalId = $data['external_id'] ?? null;
        $status = $data['status'] ?? 'pending';
        $payload = $data['payload'] ?? $request->all();
        $eventId = $data['event_id'] ?? $request->input('id');
        $eventType = $data['event_type'] ?? $request->input('type');

        Log::shareContext(['event_id' => $eventId, 'event_type' => $eventType, 'payment_uuid' => $localUuid]);

        $processed = false;

        // 1. Wrap everything in a Database Transaction with Row-Level Locking
        \Illuminate\Support\Facades\DB::transaction(function () use ($localUuid, $data, $status, $payload, $eventId, $eventType, $externalId, $request, &$processed) {
            
            Log::debug("Starting database transaction for payment update");

            // Re-find within transaction with FOR UPDATE to prevent race conditions
            $payment = Payment::where('uuid', $localUuid)->lockForUpdate()->first();
            
            if (!$payment) {
                Log::warning("Payment reconciliation failed: UUID not found in database", [
                    'uuid' => $localUuid,
                ]);
                return; // Return from transaction closure
            }

            Log::shareContext(['internal_payment_id' => $payment->id]);

            // 2. Idempotency Check (Scoped to payment and protected by row lock)
            if ($eventId && PaymentLog::where('event_id', $eventId)->where('payment_id', $payment->id)->exists()) {
                Log::info("Duplicate event detected inside locked transaction: Skipping process");
                $processed = true; // Still counts as handled successfully to gateway
                return; // Exit transaction closure smoothly
            }

            // Logic Exploit Protection: Verify captured amount matches expected DB amount
            if ($status === Payment::STATUS_PAID && isset($data['captured_amount'])) {
                if ((float)$data['captured_amount'] < (float)$payment->amount) {
                    Log::error("SECURITY ALERT: Stripe captured amount mismatch!", [
                        'payment_uuid' => $payment->uuid,
                        'expected' => $payment->amount,
                        'captured' => $data['captured_amount']
                    ]);
                    
                    // Force fail the transaction and record this high-risk event
                    $status = Payment::STATUS_FAILED;
                    $payload['SECURITY_WARNING'] = 'Captured amount was less than expected amount.';
                }
            }

            // Calculate fees correctly — now uses REAL Stripe fee from BalanceTransaction
            $fee = $data['fee'] ?? $payment->fee ?? 0;
            $netAmount = $data['net_amount'] ?? ($payment->amount - $fee);

            // Update Metadata always, even if status transition fails
            $updateData = [
                'webhook_payload' => $payload,
                'fee' => $fee,
                'net_amount' => $netAmount,
                'fee_bearer' => $data['fee_bearer'] ?? $payment->fee_bearer ?? 'customer',
                'risk_score' => $data['risk_score'] ?? $payment->risk_score,
                'is_fraud' => $data['is_fraud'] ?? $payment->is_fraud,
                'customer_email' => $data['customer_email'] ?? $payment->customer_email,
            ];

            // Multi-currency: Update if Stripe provides customer's local currency (presentment)
            if (!empty($data['original_currency'])) {
                $updateData['original_currency'] = $data['original_currency'];
                $updateData['exchange_rate'] = $data['exchange_rate'] ?? $payment->exchange_rate ?? 1.0;
            }
            if (!empty($data['original_amount'])) {
                $updateData['original_amount'] = $data['original_amount'];
            }

            // Settlement tracking: mark as settled when paid (funds captured by Stripe)
            if ($status === Payment::STATUS_PAID) {
                $updateData['settlement_status'] = 'settled';
                $updateData['settled_at'] = now();
                $updateData['settlement_reference'] = $data['settlement_reference'] ?? $payment->settlement_reference;
            }

            // Auto-generate notes from webhook lifecycle events
            $existingNotes = $payment->notes ?? '';
            $timestamp = now()->format('Y-m-d H:i:s');
            $newNote = "[{$timestamp}] Webhook: {$eventType}";
            if ($status === Payment::STATUS_PAID) {
                $newNote .= " — Payment confirmed by gateway.";
            } elseif ($status === Payment::STATUS_REFUNDED) {
                $newNote .= " — Full refund processed.";
            } elseif ($status === Payment::STATUS_PARTIALLY_REFUNDED) {
                $refundAmt = $data['refund_amount'] ?? 'unknown';
                $newNote .= " — Partial refund of {$refundAmt} {$payment->currency}.";
            } elseif ($status === Payment::STATUS_FAILED) {
                $newNote .= " — Payment failed.";
            }
            $updateData['notes'] = trim($existingNotes . "\n" . $newNote);

            // 4. Use state machine transition logic
            $transitioned = $payment->transitionTo($status, $updateData);

            // If state machine blocked (e.g. paid→paid), still save enrichment data
            // This handles the case where checkout.session.completed arrives AFTER
            // payment_intent.succeeded — the email and other metadata must still be persisted
            if (!$transitioned) {
                $enrichment = array_filter([
                    'customer_email' => $data['customer_email'] ?? null,
                    'risk_score' => $data['risk_score'] ?? null,
                    'settlement_reference' => $data['settlement_reference'] ?? null,
                    'original_currency' => $data['original_currency'] ?? null,
                    'original_amount' => $data['original_amount'] ?? null,
                    'exchange_rate' => $data['exchange_rate'] ?? null,
                    'fee' => $data['fee'] ?: null,
                    'net_amount' => $data['net_amount'] ?? null,
                ], fn($v) => $v !== null);

                if (!empty($enrichment)) {
                    $payment->update($enrichment);
                    Log::info("Enrichment data saved despite blocked transition", array_keys($enrichment));
                }
            }

            $transaction = PaymentTransaction::updateOrCreate(
                [
                    'payment_id' => $payment->id,
                    'external_id' => $externalId,
                ],
                [
                    'status' => $status,
                    'amount' => $payment->amount,
                    'currency' => $payment->currency,
                    'payload' => $payload
                ]
            );

            if ($status === Payment::STATUS_REFUNDED || $status === Payment::STATUS_PARTIALLY_REFUNDED) {
                // Use the individual Stripe refund ID (re_xxx) — NOT the charge ID (ch_xxx)
                // Fallback uses event ID (unique per webhook) to avoid dedup issues
                $refundId = $data['refund_id'] ?? 'evt_refund_' . $eventId;
                $individualAmount = $data['last_refund_amount'] ?? $data['refund_amount'] ?? $payment->amount;

                \App\Models\Refund::firstOrCreate(
                    [
                        'external_refund_id' => $refundId,
                    ],
                    [
                        'payment_id' => $payment->id,
                        'payment_transaction_id' => $transaction->id,
                        'amount' => $individualAmount,
                        'currency' => $payment->currency,
                        'status' => 'completed',
                        'reason' => $data['refund_reason'] ?? 'Webhook triggered refund',
                    ]
                );
            }

            PaymentLog::create([
                'event_id' => $eventId,
                'payment_id' => $payment->id,
                'event_type' => $eventType ?? 'webhook_' . $status,
                'payload' => $payload,
                'ip_address' => $request->ip(),
                'is_verified' => $data['is_verified'] ?? false,
                'signature' => $data['signature'] ?? null,
                'processed' => true,
                'processed_at' => now(),
                'retry_count' => 0,
            ]);

            Log::info("Payment reconciled effectively", [
                'old_status' => $payment->getOriginal('status'),
                'new_status' => $status
            ]);
            
            $processed = true;
        });

        if (!$processed) {
            return response()->json(['error' => 'Payment not found'], 404);
        }

        Log::info("Webhook handled successfully - Response sent to gateway");
        return response()->json(['message' => 'Webhook Handled successfully'], 200);
    }
}
