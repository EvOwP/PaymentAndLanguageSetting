<?php

namespace App\Console\Commands\Payments;

use App\Models\Payment;
use App\Services\PaymentGateway\GatewayFactory;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SyncStatus extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'payments:sync-status {--payment= : Specific payment UUID to sync}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Synchronize pending payment statuses with gateway APIs';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $query = Payment::whereIn('status', [Payment::STATUS_PENDING, Payment::STATUS_PROCESSING])
            ->where('created_at', '<', now()->subMinutes(5)) // Only sync after 5 mins to allow webhooks
            ->whereHas('gateway', function($q) {
                $q->where('is_manual', false);
            });

        if ($this->option('payment')) {
            $query = Payment::where('uuid', $this->option('payment'));
        }

        $payments = $query->get();

        if ($payments->isEmpty()) {
            $this->info("No pending payments found for synchronization.");
            return;
        }

        $this->info("Found {$payments->count()} payments to sync...");

        foreach ($payments as $payment) {
            $this->syncPayment($payment);
        }

        $this->info("Sync completed.");
    }

    protected function syncPayment(Payment $payment)
    {
        Log::info("Cron Sync triggered for payment", ['uuid' => $payment->uuid]);
        
        try {
            $driver = GatewayFactory::make($payment->gateway);
            $result = $driver->checkStatus($payment);

            if (isset($result['status']) && $result['status'] !== $payment->status) {
                $this->warn("Payment {$payment->uuid} status changed: {$payment->status} -> {$result['status']}");
                
                $payment->transitionTo($result['status'], [
                    'notes' => ($payment->notes ? $payment->notes . "\n" : "") . "Auto-synced via Cron at " . now()
                ]);

                // Record the sync event
                $payment->logs()->create([
                    'event_type' => 'cron_sync_update',
                    'payload' => $result,
                    'ip_address' => '127.0.0.1'
                ]);
            } else {
                $this->line("Payment {$payment->uuid} remains [{$payment->status}]");
            }
        } catch (\Exception $e) {
            Log::error("Sync failed for payment {$payment->uuid}", [
                'error' => $e->getMessage()
            ]);
            $this->error("Error syncing {$payment->uuid}: {$e->getMessage()}");
        }
    }
}
