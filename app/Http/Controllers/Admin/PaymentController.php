<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use Illuminate\Http\Request;

class PaymentController extends Controller
{
    public function index()
    {
        $payments = Payment::with(['gateway', 'transactions'])
            ->latest()
            ->paginate(15);

        return view('admin.payments.index', compact('payments'));
    }

    public function show(Payment $payment)
    {
        $payment->load(['gateway', 'transactions', 'logs', 'refunds', 'user']);
        return view('admin.payments.show', compact('payment'));
    }

    public function approveManual(Payment $payment)
    {
        if (!$payment->gateway->is_manual) {
            return back()->with('error', 'This can only be used for manual payments.');
        }

        if (!$payment->transitionTo(Payment::STATUS_PAID)) {
            return back()->with('error', "Cannot approve: payment status '{$payment->status}' does not allow transition to 'paid'.");
        }

        $payment->logs()->create([
            'event_type' => 'manual_approval',
            'payload' => ['admin_id' => auth()->id()],
            'ip_address' => request()->ip()
        ]);

        return back()->with('success', 'Payment approved successfully.');
    }

    public function rejectManual(Payment $payment)
    {
        if (!$payment->gateway->is_manual) {
            return back()->with('error', 'This can only be used for manual payments.');
        }

        if (!$payment->transitionTo(Payment::STATUS_FAILED)) {
            return back()->with('error', "Cannot reject: payment status '{$payment->status}' does not allow transition to 'failed'.");
        }

        $payment->logs()->create([
            'event_type' => 'manual_rejection',
            'payload' => ['admin_id' => auth()->id()],
            'ip_address' => request()->ip()
        ]);

        return back()->with('success', 'Payment rejected.');
    }

    public function refund(Payment $payment, Request $request)
    {
        $request->validate([
            'amount' => 'nullable|numeric|min:0.01|max:' . $payment->amount,
            'reason' => 'nullable|string|max:255'
        ]);

        if (!in_array($payment->status, [Payment::STATUS_PAID, Payment::STATUS_PARTIALLY_REFUNDED])) {
            return back()->with('error', 'Only paid or partially refunded payments can be refunded.');
        }

        try {
            return \Illuminate\Support\Facades\DB::transaction(function () use ($payment, $request) {
                // Lock for update
                $payment = Payment::where('id', $payment->id)->lockForUpdate()->first();

                $driver = \App\Services\PaymentGateway\GatewayFactory::make($payment->gateway);
                
                $result = $driver->refund($payment, $request->amount, $request->reason);

                // Determine new status (Driver might return 'refunded' or 'partially_refunded')
                $newStatus = $result['status'] ?? Payment::STATUS_REFUNDED;
                
                $payment->transitionTo($newStatus);

                $payment->refunds()->create([
                    'external_refund_id' => $result['external_refund_id'] ?? null,
                    'amount' => $result['amount'] ?? $payment->amount,
                    'currency' => $payment->currency,
                    'reason' => $request->reason ?? 'Admin requested refund',
                    'status' => 'completed'
                ]);

                $payment->logs()->create([
                    'event_type' => 'admin_refund_trigger',
                    'payload' => array_merge($result, ['admin_id' => auth()->id()]),
                    'ip_address' => request()->ip()
                ]);

                return back()->with('success', 'Refund processed successfully.');
            });
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error("Refund trigger failed: " . $e->getMessage(), [
                'payment_id' => $payment->id,
                'trace' => $e->getTraceAsString()
            ]);
            return back()->with('error', 'Refund failed: ' . $e->getMessage());
        }
    }

    public function updateNotes(Request $request, Payment $payment)
    {
        $request->validate(['note' => 'required|string|max:1000']);

        $timestamp = now()->format('Y-m-d H:i:s');
        $adminName = auth()->user()->name ?? 'Admin';
        $newNote = "[{$timestamp}] {$adminName}: {$request->note}";

        $existingNotes = $payment->notes ?? '';
        $payment->update([
            'notes' => trim($existingNotes . "\n" . $newNote),
        ]);

        return back()->with('success', __('Note saved successfully.'));
    }
}
