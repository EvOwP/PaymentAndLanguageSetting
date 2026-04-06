<?php

namespace App\Services\PaymentGateway;

use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PaypalDriver extends GatewayDriver
{
    public function process(Payment $payment, array $data)
    {
        $accessToken = $this->getAccessToken();
        if (!$accessToken) {
            throw new \Exception('Could not authenticate with PayPal.');
        }

        $baseUrl = $this->getApiBaseUrl();
        
        $response = Http::withToken($accessToken)
            ->post($baseUrl . '/v2/checkout/orders', [
                'intent' => 'CAPTURE',
                'purchase_units' => [[
                    'reference_id' => $payment->uuid,
                    'amount' => [
                        'currency_code' => strtoupper($payment->currency),
                        'value' => number_format($payment->amount, 2, '.', ''),
                    ],
                    'custom_id' => $payment->uuid,
                ]],
                'application_context' => [
                    'return_url' => route('checkout') . '?status=success&uuid=' . $payment->uuid,
                    'cancel_url' => route('checkout') . '?status=cancel&uuid=' . $payment->uuid,
                    'brand_name' => config('app.name'),
                    'user_action' => 'PAY_NOW',
                ]
            ]);

        if ($response->failed()) {
            Log::error('PayPal Order Creation Failed', [
                'response' => $response->json(),
                'status' => $response->status()
            ]);
            throw new \Exception('PayPal order creation failed.');
        }

        $order = $response->json();
        $approveUrl = collect($order['links'])->where('rel', 'approve')->first()['href'] ?? null;

        if (!$approveUrl) {
            throw new \Exception('No approval URL found in PayPal response.');
        }

        return [
            'type' => 'redirect',
            'url' => $approveUrl,
            'external_id' => $order['id']
        ];
    }

    public function handleWebhook(Request $request): array
    {
        $payload = $request->all();
        $event = $payload['event_type'] ?? '';

        // 1. Signature Verification
        $isVerified = false;
        if (!$this->verifyWebhookSignature($request)) {
            Log::warning('PayPal Webhook signature verification failed or skipped due to missing Webhook ID.');
            if ($this->getCred('PAYPAL_WEBHOOK_ID')) {
                return [];
            }
        } else {
            $isVerified = true;
        }

        $status = 'pending';
        $externalId = null;

        // Handle order approved - we must CAPTURE it to get money
        if ($event === 'CHECKOUT.ORDER.APPROVED') {
            $orderId = $payload['resource']['id'] ?? null;
            if ($orderId) {
                // Pass validation state down to capture response
                $captureData = $this->captureOrder($orderId, $payload);
                if (!empty($captureData)) {
                    $captureData['is_verified'] = $isVerified;
                    $captureData['signature'] = $request->header('PAYPAL-TRANSMISSION-SIG');
                }
                return $captureData;
            }
        }

        // Handle capture events (after capture is done)
        if ($event === 'PAYMENT.CAPTURE.COMPLETED') {
            $status = 'paid';
        } elseif ($event === 'PAYMENT.CAPTURE.DENIED' || $event === 'CHECKOUT.ORDER.VOIDED') {
            $status = 'failed';
        } elseif ($event === 'PAYMENT.CAPTURE.REFUNDED') {
            $status = 'refunded';
        }

        $resource = $payload['resource'] ?? [];
        $externalId = $resource['id'] ?? null;
        
        $localUuid = $resource['custom_id'] ?? 
                    $resource['purchase_units'][0]['custom_id'] ?? 
                    $resource['purchase_units'][0]['reference_id'] ?? 
                    ($payload['resource']['parent_payment'] ?? null);

        return [
            'external_id' => $externalId,
            'local_uuid' => $localUuid,
            'status' => $status,
            'fee' => $resource['seller_receivable_breakdown']['paypal_fee']['value'] ?? 0,
            'event_id' => $payload['id'] ?? null,
            'event_type' => $event,
            'payload' => $payload,
            'is_verified' => $isVerified,
            'signature' => $request->header('PAYPAL-TRANSMISSION-SIG')
        ];
    }

    /**
     * Capture an approved order
     */
    private function captureOrder(string $orderId, array $payload): array
    {
        $accessToken = $this->getAccessToken();
        if (!$accessToken) {
            return [];
        }

        $baseUrl = $this->getApiBaseUrl();
        $response = Http::withToken($accessToken)
            ->withHeaders([
                'PayPal-Request-Id' => $orderId,
                'Content-Type' => 'application/json'
            ])
            ->withBody('{}', 'application/json')
            ->post($baseUrl . "/v2/checkout/orders/{$orderId}/capture");

        if ($response->failed()) {
            $errorJson = $response->json();
            $issue = $errorJson['details'][0]['issue'] ?? '';
            // If already captured by another process (finalize), it's fine.
            if ($issue === 'ORDER_ALREADY_CAPTURED') {
                Log::info('PayPal Order already captured.', ['order_id' => $orderId]);
                return [];
            }
            Log::error('PayPal Capture Failed', ['order_id' => $orderId, 'response' => $errorJson]);
            return [];
        }

        $captureData = $response->json();
        $capture = $captureData['purchase_units'][0]['payments']['captures'][0] ?? [];
        
        $status = 'pending';
        $resStatus = $capture['status'] ?? '';
        if ($resStatus === 'COMPLETED') {
            $status = 'paid';
        } elseif ($resStatus === 'DECLINED' || $resStatus === 'FAILED') {
            $status = 'failed';
        }

        return [
            'external_id' => $capture['id'] ?? $orderId,
            'local_uuid' => $capture['custom_id'] ?? $captureData['purchase_units'][0]['reference_id'] ?? null,
            'status' => $status,
            'fee' => $capture['seller_receivable_breakdown']['paypal_fee']['value'] ?? 0,
            'event_id' => $payload['id'],
            'event_type' => 'INTERNAL.CAPTURE.' . $resStatus,
            'payload' => array_merge($payload, ['capture_result' => $captureData])
        ];
    }

    /**
     * Verify the authenticity of the webhook call
     */
    private function verifyWebhookSignature(Request $request): bool
    {
        $webhookId = $this->getCred('PAYPAL_WEBHOOK_ID');
        if (!$webhookId) {
            return false;
        }

        $accessToken = $this->getAccessToken();
        if (!$accessToken) {
            return false;
        }

        $baseUrl = $this->getApiBaseUrl();
        
        $response = Http::withToken($accessToken)
            ->post($baseUrl . '/v1/notifications/verify-webhook-signature', [
                'auth_algo'         => $request->header('PAYPAL-AUTH-ALGO'),
                'cert_url'          => $request->header('PAYPAL-CERT-URL'),
                'transmission_id'   => $request->header('PAYPAL-TRANSMISSION-ID'),
                'transmission_sig'  => $request->header('PAYPAL-TRANSMISSION-SIG'),
                'transmission_time' => $request->header('PAYPAL-TRANSMISSION-TIME'),
                'webhook_id'        => $webhookId,
                'webhook_event'     => $request->all(),
            ]);

        return $response->successful() && $response->json('verification_status') === 'SUCCESS';
    }

    private function getAccessToken()
    {
        $clientId = $this->getCred('PAYPAL_CLIENT_ID');
        $clientSecret = $this->getCred('PAYPAL_CLIENT_SECRET');

        if (!$clientId || !$clientSecret) {
            Log::error('PayPal credentials missing.');
            return null;
        }

        $baseUrl = $this->getApiBaseUrl();
        $response = Http::asForm()
            ->withBasicAuth($clientId, $clientSecret)
            ->post($baseUrl . '/v1/oauth2/token', [
                'grant_type' => 'client_credentials'
            ]);

        if ($response->failed()) {
            Log::error('PayPal Auth Failed', ['response' => $response->json()]);
            return null;
        }

        return $response->json()['access_token'] ?? null;
    }

    public function finalize(Payment $payment, array $data): array
    {
        $transaction = $payment->transactions()->where('status', 'pending')->first();
        $payload = $transaction->payload ?? [];
        $orderId = $payload['external_id'] ?? null;

        if (!$orderId) {
            return ['status' => 'pending'];
        }

        return $this->captureOrder($orderId, ['id' => 'internal_sync_' . uniqid()]);
    }

    private function getApiBaseUrl()
    {
        $mode = $this->getCred('PAYPAL_MODE') ?? 'sandbox';
        return ($mode === 'live') ? 'https://api-m.paypal.com' : 'https://api-m.sandbox.paypal.com';
    }
}
