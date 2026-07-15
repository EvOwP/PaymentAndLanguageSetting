<?php

namespace App\Services\PaymentGateway;

use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PaystackDriver extends GatewayDriver
{
    private function getSecretKey()
    {
        $key = $this->getCred('PAYSTACK_SECRET_KEY');
        if (!$key) {
            throw new \Exception('Paystack Secret Key is missing.');
        }
        return $key;
    }

    public function process(Payment $payment, array $data)
    {
        $secretKey = $this->getSecretKey();

        // Need customer email
        $email = $data['email'] ?? $payment->user->email ?? 'customer@example.com';

        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $secretKey,
            'Content-Type' => 'application/json',
        ])->post('https://api.paystack.co/transaction/initialize', [
            'email' => $email,
            'amount' => $payment->amount * 100, // Paystack uses kobo/cents
            'currency' => strtoupper($payment->currency),
            'reference' => $payment->uuid,
            'callback_url' => route('checkout') . '?status=success&uuid=' . $payment->uuid,
            'metadata' => [
                'payment_uuid' => $payment->uuid,
                'cancel_action' => route('checkout') . '?status=cancel&uuid=' . $payment->uuid,
            ],
        ]);

        if ($response->failed()) {
            throw new \Exception('Paystack initialization failed: ' . $response->body());
        }

        $result = $response->json();

        if (!($result['status'] ?? false)) {
            throw new \Exception('Paystack error: ' . ($result['message'] ?? 'Unknown error'));
        }

        return [
            'type' => 'redirect',
            'url' => $result['data']['authorization_url'],
            'session_id' => $result['data']['reference']
        ];
    }

    public function finalize(Payment $payment, array $data): array
    {
        $secretKey = $this->getCred('PAYSTACK_SECRET_KEY');
        if (!$secretKey) {
            return ['status' => $payment->status];
        }

        $reference = $payment->uuid;

        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $secretKey,
            ])->get("https://api.paystack.co/transaction/verify/{$reference}");

            if ($response->successful()) {
                $result = $response->json();
                
                if (($result['status'] ?? false) && isset($result['data'])) {
                    $verifyData = $result['data'];
                    
                    if ($verifyData['status'] === 'success') {
                        Log::info("Paystack finalize: Transaction confirmed as paid", [
                            'payment_uuid' => $payment->uuid,
                            'reference' => $reference
                        ]);

                        return [
                            'status' => 'paid',
                            'external_id' => $verifyData['id'] ?? $verifyData['reference'],
                            'captured_amount' => $verifyData['amount'] / 100,
                            'customer_email' => $verifyData['customer']['email'] ?? null,
                            'event_id' => 'sync_' . ($verifyData['id'] ?? $verifyData['reference']),
                            'event_type' => 'transaction.verify',
                            'payload' => $result
                        ];
                    }
                }
            }

            Log::debug("Paystack finalize: Transaction not yet paid", [
                'payment_uuid' => $payment->uuid,
                'response' => $response->json() ?? null
            ]);
            return ['status' => $payment->status];

        } catch (\Exception $e) {
            Log::warning("Paystack finalize: Failed to verify transaction", [
                'error' => $e->getMessage(),
                'payment_uuid' => $payment->uuid
            ]);
            return ['status' => $payment->status];
        }
    }

    public function handleWebhook(Request $request): array
    {
        $secretKey = $this->getCred('PAYSTACK_SECRET_KEY');
        if (!$secretKey) return [];

        $payload = $request->getContent();
        $paystackSignature = $request->header('x-paystack-signature');

        // Verify Signature
        if (!$paystackSignature || $paystackSignature !== hash_hmac('sha512', $payload, $secretKey)) {
            Log::error("Paystack Webhook Signature Verification Failed");
            return [];
        }

        $event = json_decode($payload, true);
        if (!$event) return [];

        $eventType = $event['event'] ?? null;
        $data = $event['data'] ?? [];

        // Only process these specific events
        $criticalEvents = [
            'charge.success',
            'refund.processed',
            'refund.pending',
            'refund.failed'
        ];

        if (!in_array($eventType, $criticalEvents)) {
            return [
                'event_id' => $data['id'] ?? null,
                'event_type' => $eventType,
                'status' => 'ignored',
                'payload' => $event,
                'is_verified' => true,
                'signature' => $paystackSignature
            ];
        }

        $status = null;
        switch ($eventType) {
            case 'charge.success':
                $status = 'paid';
                break;
            case 'refund.failed':
                $status = 'failed';
                break;
            // status for refund.processed and refund.pending will be determined dynamically below
        }

        $capturedAmount = ($data['transaction']['amount'] ?? $data['amount'] ?? 0) / 100;
        
        // Robust UUID extraction for both charge and refund events
        $localUuid = $data['metadata']['payment_uuid'] 
            ?? $data['transaction']['metadata']['payment_uuid'] 
            ?? $data['transaction']['reference'] 
            ?? $data['transaction_reference']
            ?? ($eventType === 'charge.success' ? ($data['reference'] ?? null) : null);

        // Refund extraction & Status determination
        $refundAmount = null; 
        $lastRefundAmount = null;
        $refundId = null;
        $refundReason = 'Paystack Webhook Refund';

        if (in_array($eventType, ['refund.processed', 'refund.pending'])) {
            $lastRefundAmount = ($data['amount'] ?? 0) / 100;
            $refundId = $data['id'] ?? null;
            $refundReason = $data['merchant_note'] ?? $data['customer_note'] ?? 'Paystack Webhook Refund';

            // Determine if partial or full refund by checking against current payment records
            $payment = \App\Models\Payment::where('uuid', $localUuid)->first();
            if ($payment) {
                $totalRefundedSoFar = $payment->total_refunded;
                // If the sum of previous refunds + this one is less than the total, it's partial
                $status = (($totalRefundedSoFar + $lastRefundAmount) < $payment->amount) 
                    ? \App\Models\Payment::STATUS_PARTIALLY_REFUNDED 
                    : \App\Models\Payment::STATUS_REFUNDED;
            } else {
                $status = \App\Models\Payment::STATUS_REFUNDED; 
            }
        }

        if (in_array($eventType, ['refund.processed', 'refund.pending'])) {
            $fee = ($data['transaction']['fees'] ?? $data['fees'] ?? 0) / 100;
            $customerEmail = $data['customer']['email'] ?? $data['transaction']['customer']['email'] ?? null;
        } else {
            $fee = ($data['fees'] ?? 0) / 100;
            $customerEmail = $data['customer']['email'] ?? null;
        }
        $netAmount = $capturedAmount - $fee;
        
        $feeBearer = $this->getCred('FEE_BEARER') ?? 'customer';

        return [
            'external_id' => $data['id'] ?? $data['reference'] ?? null,
            'local_uuid' => $localUuid,
            'status' => $status,
            'fee' => $fee,
            'net_amount' => $netAmount,
            'fee_bearer' => $feeBearer,
            'captured_amount' => $capturedAmount,
            'original_currency' => $data['currency'] ?? null,
            'original_amount' => $capturedAmount,
            'refund_amount' => $refundAmount,
            'last_refund_amount' => $lastRefundAmount,
            'refund_id' => $refundId,
            'refund_reason' => $refundReason,
            'event_id' => $data['id'] ?? null,
            'event_type' => $eventType,
            'risk_score' => null,
            'is_fraud' => false,
            'customer_email' => $customerEmail,
            'payload' => $event,
            'is_verified' => true,
            'signature' => $paystackSignature
        ];
    }

    public function refund(Payment $payment, $amount = null, $reason = null): array
    {
        $secretKey = $this->getSecretKey();

        // 1. Find the last successful transaction to get the relevant ID
        $transaction = $payment->transactions()
            ->whereIn('status', ['paid', 'partially_refunded'])
            ->latest()
            ->first();

        // Paystack refund can use transaction reference
        if (!$transaction || empty($transaction->external_id)) {
            $externalId = $payment->uuid;
        } else {
            $externalId = $transaction->external_id;
        }

        $refundData = [
            'transaction' => $externalId,
            'merchant_note' => $reason ?? 'requested_by_customer',
        ];

        if ($amount) {
            $refundData['amount'] = $amount * 100; // to kobo
        }

        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $secretKey,
            'Content-Type' => 'application/json',
        ])->post('https://api.paystack.co/refund', $refundData);

        if ($response->failed()) {
            throw new \Exception('Paystack refund failed: ' . $response->body());
        }

        $result = $response->json();

        if (!($result['status'] ?? false)) {
            throw new \Exception('Paystack refund error: ' . ($result['message'] ?? 'Unknown error'));
        }

        $data = $result['data'];
        $refundedAmount = $data['amount'] / 100;
        
        $newStatus = ($refundedAmount < $payment->amount) ? Payment::STATUS_PARTIALLY_REFUNDED : Payment::STATUS_REFUNDED;

        return [
            'status' => $newStatus,
            'external_refund_id' => $data['id'] ?? null,
            'amount' => $refundedAmount,
            'payload' => $result
        ];
    }

    public function checkStatus(Payment $payment): array
    {
        $secretKey = $this->getCred('PAYSTACK_SECRET_KEY');
        if (!$secretKey) throw new \Exception('Paystack Secret Key is missing.');

        $reference = $payment->uuid;

        Log::debug("Cron Sync: Checking Paystack status", ['ext_id' => $reference, 'payment_uuid' => $payment->uuid]);

        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $secretKey,
            ])->get("https://api.paystack.co/transaction/verify/{$reference}");

            if ($response->successful()) {
                $result = $response->json();
                
                if (($result['status'] ?? false) && isset($result['data'])) {
                    $data = $result['data'];
                    
                    if ($data['status'] === 'success') {
                        return ['status' => 'paid', 'external_id' => $data['id'] ?? $data['reference']];
                    } elseif ($data['status'] === 'failed') {
                        return ['status' => 'failed', 'external_id' => $data['id'] ?? $data['reference']];
                    }
                }
            }
            
            return ['status' => $payment->status, 'error' => 'Not found or not paid'];

        } catch (\Exception $e) {
            Log::error("Paystack status sync check failed", ['error' => $e->getMessage()]);
            return ['status' => $payment->status, 'error' => $e->getMessage()];
        }
    }
}
