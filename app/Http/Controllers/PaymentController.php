<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use App\Models\PaymentGateway;
use App\Models\PaymentTransaction;
use App\Services\PaymentGateway\GatewayFactory;
use Illuminate\Http\Request;

class PaymentController extends Controller
{
    public function index()
    {
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

        // 1. Create the persistent Payment record
        $payment = Payment::create([
            'payment_gateway_id' => $gateway->id,
            'amount' => $request->amount,
            'currency' => $gateway->currency,
            'status' => 'pending',
            'idempotency_key' => uniqid('pay_', true),
        ]);

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
        $result = $driver->process($payment, $request->all());

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
