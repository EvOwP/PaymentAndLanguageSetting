<?php

namespace App\Services;

use App\Models\Payment;
use App\Models\PaymentLog;
use App\Models\PaymentTransaction;
use App\Models\Refund;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PaymentReconciliationService
{
    /**
     * Reconcile a payment based on normalized data from a gateway driver.
     * 
     * @param Payment $payment
     * @param array $data Normalized data from driver
     * @param string|null $eventId
     * @param string|null $eventType
     * @param array $payload Raw payload
     * @param string|null $ip
     * @param string|null $signature
     * @param bool $isVerified
     * @return bool True if processed successfully (including duplicates)
     */
    public function reconcile(
        Payment $payment,
        array $data,
        $eventId = null,
        $eventType = null,
        array $payload = [],
        $ip = null,
        $signature = null,
        $isVerified = false
    ): bool {
        $processed = false;

        DB::transaction(function () use ($payment, $data, $payload, $eventId, $eventType, $ip, $signature, $isVerified, &$processed) {
            
            // Re-find with lock to prevent race conditions
            $payment = Payment::where('id', $payment->id)->lockForUpdate()->first();

            // Idempotency Check
            if ($eventId && PaymentLog::where('event_id', $eventId)
                ->where('payment_id', $payment->id)
                ->where('processed', true)
                ->exists()) {
                Log::info("Duplicate event detected: Skipping process", ['event_id' => $eventId, 'payment_id' => $payment->id]);
                $processed = true;
                return;
            }

            $status = $data['status'] ?? 'pending';
            $externalId = $data['external_id'] ?? null;

            // Security Check: Verify captured amount
            if ($status === Payment::STATUS_PAID && isset($data['captured_amount'])) {
                if ((float)$data['captured_amount'] < (float)$payment->amount) {
                    Log::error("SECURITY ALERT: Captured amount mismatch!", [
                        'payment_uuid' => $payment->uuid,
                        'expected' => $payment->amount,
                        'captured' => $data['captured_amount']
                    ]);
                    $status = Payment::STATUS_FAILED;
                    $payload['SECURITY_WARNING'] = 'Captured amount was less than expected amount.';
                }
            }

            $fee = $data['fee'] ?? $payment->fee ?? 0;
            $netAmount = $data['net_amount'] ?? ($payment->amount - $fee);

            $updateData = [
                'webhook_payload' => $payload,
                'fee' => $fee,
                'net_amount' => $netAmount,
                'fee_bearer' => $data['fee_bearer'] ?? $payment->fee_bearer ?? 'customer',
                'risk_score' => $data['risk_score'] ?? $payment->risk_score,
                'is_fraud' => $data['is_fraud'] ?? $payment->is_fraud,
                'customer_email' => $data['customer_email'] ?? $payment->customer_email,
            ];

            if (!empty($data['original_currency'])) {
                $updateData['original_currency'] = $data['original_currency'];
                $updateData['exchange_rate'] = $data['exchange_rate'] ?? $payment->exchange_rate ?? 1.0;
            }
            if (!empty($data['original_amount'])) {
                $updateData['original_amount'] = $data['original_amount'];
            }

            if ($status === Payment::STATUS_PAID) {
                $updateData['settlement_status'] = 'settled';
                $updateData['settled_at'] = now();
                $updateData['settlement_reference'] = $data['settlement_reference'] ?? $payment->settlement_reference;
            }

            // Auto-generate notes
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

            // Transition
            $transitioned = $payment->transitionTo($status, $updateData);

            if (!$transitioned) {
                $enrichment = array_filter([
                    'customer_email' => $data['customer_email'] ?? null,
                    'risk_score' => $data['risk_score'] ?? null,
                    'settlement_reference' => $data['settlement_reference'] ?? null,
                    'original_currency' => $data['original_currency'] ?? null,
                    'original_amount' => $data['original_amount'] ?? null,
                    'exchange_rate' => $data['exchange_rate'] ?? null,
                    'fee' => $data['fee'] ?? null,
                    'net_amount' => $data['net_amount'] ?? null,
                ], fn($v) => $v !== null);

                if (!empty($enrichment)) {
                    $payment->update($enrichment);
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
                $refundId = $data['refund_id'] ?? 'evt_refund_' . $eventId;
                $individualAmount = $data['last_refund_amount'] ?? $data['refund_amount'] ?? $payment->amount;

                Refund::firstOrCreate(
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

            // Update or Create the log entry
            PaymentLog::updateOrCreate(
                [
                    'event_id' => $eventId,
                    'payment_id' => $payment->id,
                ],
                [
                    'event_type' => $eventType ?? 'webhook_' . $status,
                    'payload' => $payload,
                    'ip_address' => $ip,
                    'is_verified' => $isVerified,
                    'signature' => $signature,
                    'processed' => true,
                    'processed_at' => now(),
                ]
            );

            $processed = true;
        });

        return $processed;
    }
}
