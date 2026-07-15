<?php

namespace App\Console\Commands\Payments;

use App\Models\PaymentLog;
use App\Models\PaymentGateway;
use App\Services\PaymentGateway\GatewayFactory;
use App\Services\PaymentReconciliationService;
use Illuminate\Console\Command;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class RetryWebhooks extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'payments:retry-webhooks {--limit=50 : Max number of logs to retry}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Retry processing failed webhook events from payment_logs';

    /**
     * Execute the console command.
     */
    public function handle(PaymentReconciliationService $reconciliationService)
    {
        $limit = $this->option('limit');

        $failedLogs = PaymentLog::where('processed', false)
            ->where('retry_count', '<', 5)
            ->with(['payment.gateway'])
            ->orderBy('created_at', 'asc')
            ->limit($limit)
            ->get();

        if ($failedLogs->isEmpty()) {
            $this->info("No failed webhook logs found for retry.");
            return;
        }

        $this->info("Found {$failedLogs->count()} failed logs to retry...");

        foreach ($failedLogs as $log) {
            $this->retryLog($log, $reconciliationService);
        }

        $this->info("Retry process completed.");
    }

    protected function retryLog(PaymentLog $log, PaymentReconciliationService $reconciliationService)
    {
        $log->increment('retry_count');

        $payment = $log->payment;
        if (!$payment) {
            $this->error("Log ID {$log->id} has no associated payment. Skipping.");
            return;
        }

        $gateway = $payment->gateway;
        if (!$gateway) {
            $this->error("Payment {$payment->uuid} has no associated gateway. Skipping.");
            return;
        }

        $this->line("Retrying Log ID {$log->id} (Event: {$log->event_id}) for Payment: {$payment->uuid}...");

        try {
            $driver = GatewayFactory::make($gateway);
            
            // Reconstruct a Request object for the driver
            $mockRequest = Request::create(
                route('webhooks.handle', ['gatewayName' => strtolower($gateway->name)]),
                'POST',
                [],
                [],
                [],
                ['REMOTE_ADDR' => $log->ip_address],
                json_encode($log->payload)
            );
            $mockRequest->headers->set('Stripe-Signature', $log->signature);

            $data = $driver->handleWebhook($mockRequest);

            if (empty($data)) {
                $this->warn("Driver could not normalize payload for Log ID {$log->id}.");
                return;
            }

            $processed = $reconciliationService->reconcile(
                $payment,
                $data,
                $log->event_id,
                $log->event_type,
                $log->payload,
                $log->ip_address,
                $log->signature,
                $log->is_verified
            );

            if ($processed) {
                $this->info("Successfully processed Log ID {$log->id}.");
            } else {
                $this->warn("Reconciliation returned false for Log ID {$log->id}.");
            }

        } catch (\Exception $e) {
            $this->error("Retry failed for Log ID {$log->id}: {$e->getMessage()}");
            Log::error("Webhook retry failed", [
                'log_id' => $log->id,
                'error' => $e->getMessage()
            ]);
        }
    }
}
