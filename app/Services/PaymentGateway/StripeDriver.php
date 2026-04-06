<?php

namespace App\Services\PaymentGateway;

use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class StripeDriver extends GatewayDriver
{
    public function process(Payment $payment, array $data)
    {
        $credentials = $this->gateway->credentials;
        $secretKey = $credentials['STRIPE_SECRET_KEY'] ?? null;

        if (!$secretKey) {
            throw new \Exception('Stripe Secret Key is missing.');
        }

        \Stripe\Stripe::setApiKey($secretKey);

        $session = \Stripe\Checkout\Session::create([
            'payment_method_types' => ['card'],
            'line_items' => [[
                'price_data' => [
                    'currency' => strtolower($payment->currency),
                    'product_data' => [
                        'name' => 'Order #' . $payment->uuid,
                    ],
                    'unit_amount' => $payment->amount * 100,
                ],
                'quantity' => 1,
            ]],
            'mode' => 'payment',
            'success_url' => route('checkout') . '?status=success&uuid=' . $payment->uuid,
            'cancel_url' => route('checkout') . '?status=cancel&uuid=' . $payment->uuid,
            'payment_intent_data' => [
                'metadata' => [
                    'payment_uuid' => $payment->uuid,
                ],
            ],
            'metadata' => [
                'payment_uuid' => $payment->uuid,
            ],
        ]);

        return [
            'type' => 'redirect',
            'url' => $session->url,
            'session_id' => $session->id
        ];
    }

    /**
     * Finalize payment after redirect return from Stripe Checkout.
     * Retrieves the Checkout Session to check if payment was actually completed.
     */
    public function finalize(Payment $payment, array $data): array
    {
        $credentials = $this->gateway->credentials;
        $secretKey = $credentials['STRIPE_SECRET_KEY'] ?? null;

        if (!$secretKey) {
            return ['status' => $payment->status];
        }

        \Stripe\Stripe::setApiKey($secretKey);

        // Find the session_id from the initial transaction payload
        $transaction = $payment->transactions()
            ->where('status', 'pending')
            ->latest()
            ->first();

        $sessionId = $transaction->payload['session_id'] ?? null;

        if (!$sessionId) {
            Log::debug("Stripe finalize: No session_id found in transaction payload", [
                'payment_uuid' => $payment->uuid
            ]);
            return ['status' => $payment->status];
        }

        try {
            $session = \Stripe\Checkout\Session::retrieve($sessionId);

            if ($session->payment_status === 'paid') {
                Log::info("Stripe finalize: Session confirmed as paid", [
                    'payment_uuid' => $payment->uuid,
                    'session_id' => $sessionId
                ]);
                return ['status' => 'paid'];
            }

            Log::debug("Stripe finalize: Session not yet paid", [
                'payment_uuid' => $payment->uuid,
                'payment_status' => $session->payment_status
            ]);
            return ['status' => $payment->status];

        } catch (\Exception $e) {
            Log::warning("Stripe finalize: Failed to retrieve session", [
                'error' => $e->getMessage(),
                'payment_uuid' => $payment->uuid
            ]);
            return ['status' => $payment->status];
        }
    }

    public function handleWebhook(Request $request): array
    {
        $credentials = $this->gateway->credentials;
        $secretKey = $credentials['STRIPE_SECRET_KEY'] ?? null;
        $webhookSecret = $credentials['STRIPE_WEBHOOK_SECRET'] ?? null;

        if (!$secretKey) return [];

        \Stripe\Stripe::setApiKey($secretKey);

        $payload = $request->getContent();
        $sigHeader = $request->header('Stripe-Signature');
        $event = null;

        try {
            if ($webhookSecret && $sigHeader) {
                $event = \Stripe\Webhook::constructEvent($payload, $sigHeader, $webhookSecret);
            } else {
                throw new \Exception('Webhook secret or signature missing.');
            }
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error("Stripe Webhook Signature Verification Failed: " . $e->getMessage());
            return [];
        }

        // Only process these high-priority events
        $criticalEvents = [
            'checkout.session.completed',
            'payment_intent.succeeded',
            'payment_intent.payment_failed',
            'charge.refunded'
        ];

        if (!in_array($event->type, $criticalEvents)) {
            return [
                'event_id' => $event->id,
                'event_type' => $event->type,
                'status' => 'ignored',
                'payload' => $event->toArray(),
                'is_verified' => true,
                'signature' => $sigHeader
            ];
        }

        $session = $event->data->object;
        $status = null; // Changed from pending to null to detect actionable events

        switch ($event->type) {
            case 'checkout.session.completed':
            case 'payment_intent.succeeded':
                $status = 'paid';
                break;
            case 'payment_intent.payment_failed':
                $status = 'failed';
                break;
            case 'charge.refunded':
                $status = ($session->amount_refunded < $session->amount) ? 'partially_refunded' : 'refunded';
                break;
        }

        // Calculate the captured amount based on the event object type (Security Check)
        $capturedAmount = 0;
        if (isset($session->amount_total)) {
            $capturedAmount = $session->amount_total / 100; // Checkout Session
        } elseif (isset($session->amount_received)) {
            $capturedAmount = $session->amount_received / 100; // Payment Intent
        } elseif (isset($session->amount_captured)) {
            $capturedAmount = $session->amount_captured / 100; // Charge (from PI)
        } elseif (isset($session->amount)) {
            $capturedAmount = $session->amount / 100; // Legacy Charge or generic fallback
        }

        // For refund events, we want the captured amount to reflect what was originally paid
        // to pass the security check in the controller, so we use the original amount if available.
        if ($status === 'refunded' || $status === 'partially_refunded') {
             // In refunds, we usually just want to pass the amount check or let the controller handle it
             // Actually, the security check in WebhookController only happens if $status === STATUS_PAID
        }

        Log::debug("Driver normalized event status", [
            'raw_type' => $event->type,
            'calculated_status' => $status,
            'amount_found' => $capturedAmount
        ]);

        // Standardized metadata lookup
        $localUuid = $session->metadata->payment_uuid ?? null;
        
        // If not found in primary metadata, attempt deep lookup (e.g. for charge objects)
        if (!$localUuid && isset($session->payment_intent) && is_string($session->payment_intent)) {
            try {
                $pi = \Stripe\PaymentIntent::retrieve($session->payment_intent);
                $localUuid = $pi->metadata->payment_uuid ?? null;
            } catch (\Exception $e) {}
        }

        // 3. Retrieve the original Checkout Session from Stripe API using the payment_intent
        if (!$localUuid && !empty($session->payment_intent)) {
            try {
                $sessions = \Stripe\Checkout\Session::all([
                    'payment_intent' => $session->payment_intent,
                    'limit' => 1,
                ]);
                if (!empty($sessions->data)) {
                    $localUuid = $sessions->data[0]->metadata->payment_uuid ?? null;
                }
            } catch (\Exception $e) {
                Log::warning("Stripe session lookup failed: " . $e->getMessage());
            }
        }

        // 4. DB fallback: search local payment_transactions for this payment_intent
        if (!$localUuid && !empty($session->payment_intent)) {
            $piID = $session->payment_intent;
            $matchedTransaction = \App\Models\PaymentTransaction::where('payload', 'like', '%' . $piID . '%')
                ->orWhere('external_id', $piID)
                ->first();
                
            if ($matchedTransaction) {
                $localUuid = $matchedTransaction->payment->uuid ?? null;
            }
        }
        
        // 5. Fallback for older/other metadata keys
        $localUuid = $localUuid ?? $session->metadata->order_uuid ?? null;

        // Final Filter: If critical event fails to find UUID, return 404. Technical events return 200.
        if (!$localUuid && in_array($event->type, ['checkout.session.completed', 'payment_intent.succeeded'])) {
            return []; // Skip if we can't link critical events safely
        }

        $refundAmount = null;
        $refundReason = 'Stripe Webhook Refund';

        if ($event->type === 'charge.refunded') {
            $refundAmount = ($session->amount_refunded ?? 0) / 100;
            
            $lastRefundId = null;
            $lastRefundAmount = null;
            
            // Strategy 1: Try to read directly from the Charge's embedded refunds list
            if (!empty($session->refunds->data)) {
                $refundsList = $session->refunds->data;
                $lastRefund = end($refundsList);
                $refundReason = $lastRefund->reason ?? 'Stripe Webhook Refund';
                $lastRefundId = $lastRefund->id;
                $lastRefundAmount = ($lastRefund->amount ?? 0) / 100;
            }
            
            // Strategy 2: API fallback — webhook payloads often DON'T include refunds expansion
            if (!$lastRefundId) {
                try {
                    $refunds = \Stripe\Refund::all([
                        'charge' => $session->id,
                        'limit' => 1,
                    ]);
                    if (!empty($refunds->data)) {
                        $latestRefund = $refunds->data[0];
                        $lastRefundId = $latestRefund->id;
                        $lastRefundAmount = ($latestRefund->amount ?? 0) / 100;
                        $refundReason = $latestRefund->reason ?? 'Stripe Webhook Refund';
                    }
                } catch (\Exception $e) {
                    Log::warning("Refund API fallback failed: " . $e->getMessage());
                }
            }
            
            Log::info("Refund data resolved", [
                'refund_id' => $lastRefundId,
                'individual_amount' => $lastRefundAmount,
                'cumulative_refunded' => $refundAmount,
                'source' => $lastRefundId ? 'resolved' : 'fallback_event_id',
            ]);
        }

        // Extract customer email from different Stripe object types
        $customerEmail = $session->customer_details->email   // checkout.session
            ?? $session->billing_details->email              // charge
            ?? $session->receipt_email                       // payment_intent
            ?? null;

        // Extract settlement reference (Stripe's balance_transaction ID for payout tracking)
        $settlementRef = $session->balance_transaction ?? null;

        // ── Multi-Currency: Extract the customer's REAL local currency from Stripe ──
        // Stripe provides presentment_details on charges & checkout sessions when
        // the customer's card currency differs from your Stripe account currency.
        $presentmentCurrency = strtoupper($session->presentment_details->presentment_currency ?? $session->currency ?? '');
        $presentmentAmount = isset($session->presentment_details->presentment_amount)
            ? $session->presentment_details->presentment_amount / 100
            : null;

        // Calculate the real exchange rate if currencies differ
        $exchangeRate = 1.0;
        if ($presentmentAmount && $capturedAmount && $capturedAmount > 0 && $presentmentCurrency !== strtoupper($session->currency ?? '')) {
            $exchangeRate = round($presentmentAmount / $capturedAmount, 6);
        }

        // ── Actual Stripe Fee: Fetch from BalanceTransaction API ──
        $stripeFee = 0;
        $netAmount = $capturedAmount;
        if ($settlementRef && $status === 'paid') {
            try {
                $balanceTxn = \Stripe\BalanceTransaction::retrieve($settlementRef);
                $stripeFee = ($balanceTxn->fee ?? 0) / 100;     // Stripe fee in dollars
                $netAmount = ($balanceTxn->net ?? 0) / 100;     // What you actually receive
                Log::debug("Stripe fee extracted from BalanceTransaction", [
                    'balance_txn' => $settlementRef,
                    'gross' => $capturedAmount,
                    'stripe_fee' => $stripeFee,
                    'net' => $netAmount,
                ]);
            } catch (\Exception $e) {
                Log::warning("Could not retrieve BalanceTransaction for fee: " . $e->getMessage());
            }
        }

        // fee_bearer: read from gateway config (defaults to 'customer')
        $feeBearer = $this->gateway->credentials['FEE_BEARER'] ?? 'customer';

        return [
            'external_id' => $session->id ?? null,
            'local_uuid' => $localUuid,
            'status' => $status,
            'fee' => $stripeFee,
            'net_amount' => $netAmount,
            'fee_bearer' => $feeBearer,
            'captured_amount' => $capturedAmount,
            'original_currency' => $presentmentCurrency ?: null,
            'original_amount' => $presentmentAmount,
            'exchange_rate' => $exchangeRate,
            'refund_amount' => $refundAmount,
            'refund_id' => $lastRefundId ?? null,               // re_xxxxx (unique per refund)
            'last_refund_amount' => $lastRefundAmount ?? null,   // This specific refund's amount
            'refund_reason' => $refundReason,
            'event_id' => $event->id ?? null,
            'event_type' => $event->type ?? null,
            'risk_score' => $session->outcome->risk_score ?? null,
            'is_fraud' => ($session->outcome->network_status ?? '') === 'declined_by_network' || ($session->outcome->risk_level ?? '') === 'elevated',
            'customer_email' => $customerEmail,
            'settlement_reference' => $settlementRef,
            'payload' => $event->toArray(),
            'is_verified' => true,
            'signature' => $sigHeader
        ];
    }

    public function refund(Payment $payment, $amount = null, $reason = null): array
    {
        $credentials = $this->gateway->credentials;
        $secretKey = $credentials['STRIPE_SECRET_KEY'] ?? null;
        if (!$secretKey) throw new \Exception('Stripe Secret Key is missing.');
        \Stripe\Stripe::setApiKey($secretKey);

        // 1. Find the last successful transaction to get the relevant ID
        $transaction = $payment->transactions()
            ->whereIn('status', ['paid', 'partially_refunded'])
            ->latest()
            ->first();

        if (!$transaction || empty($transaction->external_id)) {
            throw new \Exception("No valid external transaction ID found for refund.");
        }

        $refundData = [
            'reason' => 'requested_by_customer',
            'metadata' => [
                'payment_uuid' => $payment->uuid,
                'admin_reason' => $reason
            ]
        ];

        // Stripe supports both Charge IDs (ch_...) and PaymentIntent IDs (pi_...)
        // For Checkout Sessions, the external_id is usually the session or charge.
        if (strpos($transaction->external_id, 'pi_') === 0) {
            $refundData['payment_intent'] = $transaction->external_id;
        } else {
            $refundData['charge'] = $transaction->external_id;
        }

        if ($amount) {
            $refundData['amount'] = $amount * 100; // to cents
        }

        $stripeRefund = \Stripe\Refund::create($refundData);
        $refundedAmount = $stripeRefund->amount / 100;
        
        $newStatus = ($refundedAmount < $payment->amount) ? Payment::STATUS_PARTIALLY_REFUNDED : Payment::STATUS_REFUNDED;

        return [
            'status' => $newStatus,
            'external_refund_id' => $stripeRefund->id,
            'amount' => $refundedAmount,
            'payload' => $stripeRefund->toArray()
        ];
    }

    public function checkStatus(Payment $payment): array
    {
        $credentials = $this->gateway->credentials;
        $secretKey = $credentials['STRIPE_SECRET_KEY'] ?? null;
        if (!$secretKey) throw new \Exception('Stripe Secret Key is missing.');
        \Stripe\Stripe::setApiKey($secretKey);

        // Find external reference from logs or transactions
        $extId = $payment->transactions()
            ->whereNotNull('external_id')
            ->value('external_id');
        
        if (!$extId) {
            // Check if we can find it in the payload of the first pending log if transactions are empty
            $lastLog = $payment->logs()->whereNotNull('payload')->latest()->first();
            $extId = $lastLog->payload['session_id'] ?? $lastLog->payload['id'] ?? null;
        }

        if (!$extId) {
            return ['status' => $payment->status, 'error' => 'No external ID found'];
        }

        Log::debug("Cron Sync: Checking Stripe status", ['ext_id' => $extId, 'payment_uuid' => $payment->uuid]);

        try {
            $status = $payment->status;
            if (strpos($extId, 'cs_') === 0) {
                $session = \Stripe\Checkout\Session::retrieve($extId);
                if ($session->payment_status === 'paid') $status = 'paid';
            } elseif (strpos($extId, 'pi_') === 0) {
                $pi = \Stripe\PaymentIntent::retrieve($extId);
                if ($pi->status === 'succeeded') $status = 'paid';
            }

            return ['status' => $status, 'external_id' => $extId];

        } catch (\Exception $e) {
            Log::error("Stripe status sync check failed", ['error' => $e->getMessage()]);
            return ['status' => $payment->status, 'error' => $e->getMessage()];
        }
    }
}
