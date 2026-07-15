<?php

namespace App\Services\PaymentGateway;

use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class RazorpayDriver extends GatewayDriver
{
    public function process(Payment $payment, array $data)
    {
        $keyId = $this->getCred('RAZORPAY_KEY_ID');
        $keySecret = $this->getCred('RAZORPAY_KEY_SECRET');

        if (!$keyId || !$keySecret) {
            throw new \Exception('Razorpay credentials missing.');
        }

        $response = Http::withBasicAuth($keyId, $keySecret)
            ->post('https://api.razorpay.com/v1/orders', [
                'amount' => (int)($payment->amount * 100), // in paise
                'currency' => strtoupper($payment->currency),
                'receipt' => $payment->uuid,
                'notes' => [
                    'order_uuid' => $payment->uuid,
                ]
            ]);

        if ($response->failed()) {
            Log::error('Razorpay Order Creation Failed', [
                'response' => $response->json(),
                'status' => $response->status()
            ]);
            throw new \Exception('Razorpay order creation failed.');
        }

        $order = $response->json();

        return [
            'type' => 'view',
            'view' => 'razorpay_checkout', // This would be a frontend component or Blade view
            'order_id' => $order['id'],
            'amount' => $order['amount'],
            'currency' => $order['currency'],
            'key' => $keyId,
            'name' => config('app.name'),
            'notes' => $order['notes']
        ];
    }

    public function handleWebhook(Request $request): array
    {
        $payload = $request->getContent();
        $signature = $request->header('X-Razorpay-Signature');
        $webhookSecret = $this->getCred('RAZORPAY_WEBHOOK_SECRET');
        
        $isVerified = false;
        if ($webhookSecret && $signature) {
            $expectedSignature = hash_hmac('sha256', $payload, $webhookSecret);
            $isVerified = hash_equals($expectedSignature, $signature);
        }

        if (!$isVerified && $webhookSecret) {
            Log::warning('Razorpay Webhook signature verification failed.');
            return [];
        }

        $data = json_decode($payload, true);
        $event = $data['event'] ?? '';
        
        // Map Razorpay events to our statuses
        $status = 'pending';
        if (in_array($event, ['order.paid', 'payment.captured', 'payment.authorized'])) {
            $status = 'paid';
        } elseif ($event === 'payment.failed') {
            $status = 'failed';
        } elseif ($event === 'refund.processed') {
            $status = 'refunded';
        }

        $paymentEntity = $data['payload']['payment']['entity'] ?? [];
        $orderEntity = $data['payload']['order']['entity'] ?? [];
        
        $localUuid = $orderEntity['notes']['order_uuid'] ?? 
                    $paymentEntity['notes']['order_uuid'] ?? 
                    $orderEntity['receipt'] ?? null;

        $capturedAmount = ($paymentEntity['amount'] ?? 0) / 100;
        $fee = ($paymentEntity['fee'] ?? 0) / 100;
        $netAmount = ($paymentEntity['amount'] - ($paymentEntity['fee'] ?? 0)) / 100;

        return [
            'external_id' => $paymentEntity['id'] ?? $orderEntity['id'] ?? null,
            'local_uuid' => $localUuid,
            'status' => $status,
            'captured_amount' => $capturedAmount,
            'fee' => $fee,
            'net_amount' => $netAmount,
            'original_currency' => $paymentEntity['currency'] ?? null,
            'original_amount' => $capturedAmount,
            'exchange_rate' => 1.0,
            'customer_email' => $paymentEntity['email'] ?? null,
            'event_id' => $data['id'] ?? null,
            'event_type' => $event,
            'is_verified' => $isVerified,
            'signature' => $signature,
            'payload' => $data
        ];
    }

    public function finalize(Payment $payment, array $data): array
    {
        // For Razorpay, finalize often involves verifying the payment signature from the frontend
        // If the frontend passed payment_id, we can verify it here.
        $paymentId = $data['razorpay_payment_id'] ?? null;
        if (!$paymentId) return ['status' => $payment->status];

        $keyId = $this->getCred('RAZORPAY_KEY_ID');
        $keySecret = $this->getCred('RAZORPAY_KEY_SECRET');

        $response = Http::withBasicAuth($keyId, $keySecret)
            ->get("https://api.razorpay.com/v1/payments/{$paymentId}");

        if ($response->successful()) {
            $paymentData = $response->json();
            if ($paymentData['status'] === 'captured' || $paymentData['status'] === 'authorized') {
                return [
                    'status' => 'paid',
                    'external_id' => $paymentId,
                    'captured_amount' => $paymentData['amount'] / 100,
                    'customer_email' => $paymentData['email'] ?? null,
                    'payload' => $paymentData
                ];
            }
        }

        return ['status' => $payment->status];
    }

    public function refund(Payment $payment, $amount = null, $reason = null): array
    {
        $keyId = $this->getCred('RAZORPAY_KEY_ID');
        $keySecret = $this->getCred('RAZORPAY_KEY_SECRET');
        
        $transaction = $payment->transactions()->where('status', 'paid')->latest()->first();
        $paymentId = $transaction->external_id ?? null;

        if (!$paymentId) throw new \Exception('No Razorpay payment ID found for refund.');

        $refundData = ['speed' => 'normal'];
        if ($amount) {
            $refundData['amount'] = (int)($amount * 100);
        }

        $response = Http::withBasicAuth($keyId, $keySecret)
            ->post("https://api.razorpay.com/v1/payments/{$paymentId}/refund", $refundData);

        if ($response->failed()) {
            throw new \Exception('Razorpay refund failed: ' . $response->body());
        }

        $refund = $response->json();
        return [
            'status' => ($refund['amount'] / 100 < $payment->amount) ? 'partially_refunded' : 'refunded',
            'external_refund_id' => $refund['id'],
            'amount' => $refund['amount'] / 100,
            'payload' => $refund
        ];
    }
}
