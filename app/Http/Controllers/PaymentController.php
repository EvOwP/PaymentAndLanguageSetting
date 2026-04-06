<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use App\Models\PaymentGateway;
use App\Models\PaymentTransaction;
use App\Services\PaymentGateway\GatewayFactory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PaymentController extends Controller
{
    public function index(Request $request)
    {
        if ($request->has('status')) {
            $status = $request->get('status');
            $uuid = $request->get('uuid');
            $payment = Payment::where('uuid', $uuid)->first();

            if ($status === 'success' && $payment) {
                // 3. Delegate to Dynamic Driver Factory for final sync/capture
                $driver = GatewayFactory::make($payment->gateway);
                $finalData = $driver->finalize($payment, $request->all());
                
                if (isset($finalData['status']) && $finalData['status'] === 'paid') {
                    // Use state machine transition — may return false if webhook already confirmed
                    if ($payment->transitionTo('paid')) {
                        session()->now('success', __('Payment successful and confirmed!'));
                    } else {
                        // Already paid via webhook — still show success to user
                        session()->now('success', __('Payment successful and confirmed!'));
                    }
                } else {
                    session()->now('success', __('Payment processing. We will notify you once confirmed.'));
                }
            } elseif ($status === 'cancel') {
                session()->now('error', __('Payment was cancelled.'));
            }
        }

        $gateways = PaymentGateway::where('status', true)->get();
        return view('checkout', compact('gateways'));
    }

    public function process(Request $request)
    {
        $request->validate([
            'amount' => 'required|numeric|min:1',
            'gateway_id' => 'required|exists:payment_gateways,id',
            'proof' => 'nullable|image',
        ]);

        $gateway = PaymentGateway::find($request->gateway_id);
        
        $totalAmount = $request->amount + $gateway->fee;
 
        Log::info("Checkout initialization started", [
            'gateway' => $gateway->name,
            'amount' => $request->amount,
            'fee' => $gateway->fee,
            'total' => $totalAmount
        ]);

        // 1. Better Idempotency: Generate key based on user, amount, and gateway
        // This prevents double-clicks from creating multiple records for the same "intent"
        $idempotencyKey = hash('sha256', (auth()->id() ?? 'guest') . $totalAmount . $gateway->id . date('Y-m-d H:i'));

        // Check if an identical pending payment was just created (within this minute)
        $existingPayment = Payment::where('idempotency_key', $idempotencyKey)
            ->where('status', 'pending')
            ->first();

        if ($existingPayment) {
            Log::info("Idempotent check hit: Using existing pending payment", ['uuid' => $existingPayment->uuid]);
            $payment = $existingPayment;
        } else {
            $payment = Payment::create([
                'user_id' => auth()->id(),
                'payment_gateway_id' => $gateway->id,
                'amount' => $totalAmount,
                'currency' => $gateway->currency,
                'original_amount' => $request->amount,
                'original_currency' => $gateway->currency,
                'customer_email' => auth()->user()->email ?? null,
                'status' => 'pending',
                'idempotency_key' => $idempotencyKey,
            ]);
            Log::debug("Persistent payment record created", [
                'id' => $payment->id,
                'uuid' => $payment->uuid
            ]);
        }

        // 2. Handle Manual Proof 
        if ($gateway->is_manual) {
            if (!$request->hasFile('proof')) {
                return back()->with('error', 'Payment proof is required for manual gateways.');
            }
            $payment->update([
                'proof_path' => $request->file('proof')->store('payments/proofs', 'public')
            ]);
        }

        // 3. Delegate to Dynamic Driver Factory
        $driver = GatewayFactory::make($gateway);
        
        try {
            $result = $driver->process($payment, $request->all());
        } catch (\Exception $e) {
            Log::error("Driver processing failed during checkout", [
                'error' => $e->getMessage(),
                'payment_uuid' => $payment->uuid
            ]);
            return back()->with('error', 'Gateway communication error. Please try again.');
        }

        // 4. Record initial Transaction (The attempt)
        PaymentTransaction::create([
            'payment_id' => $payment->id,
            'status' => 'pending',
            'amount' => $payment->amount,
            'currency' => $payment->currency,
            'payload' => $result
        ]);

        // If gateway requires redirect (Stripe/PayPal), redirect now
        if (isset($result['type']) && $result['type'] === 'redirect') {
            return redirect()->away($result['url']);
        }

        return redirect()->route('checkout')->with('success', 'Payment initiated successfully.');
    }
}
