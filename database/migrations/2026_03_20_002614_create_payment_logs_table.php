<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('payment_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payment_id')->constrained()->onDelete('cascade');
            $table->string('event_type'); // e.g., 'webhook_received', 'status_changed', 'api_request'
            $table->json('payload')->nullable(); // The full HTTP body of the webhook
            $table->ipAddress('ip_address')->nullable();

            // Webhook Verification
            $table->boolean('is_verified')->default(false); // Verified against the gateway's secret hash
            $table->text('signature')->nullable(); // Store the sent signature header (e.g., Stripe-Signature)

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payment_logs');
    }
};
