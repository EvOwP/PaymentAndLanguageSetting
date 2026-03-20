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
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('idempotency_key')->unique()->nullable();

            // Polymorphic relation (Product, Subscription, Booking, etc.)
            $table->nullableMorphs('payable');

            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('payment_gateway_id')->constrained()->onDelete('cascade');

            // Core Pricing
            $table->decimal('amount', 15, 2); // Final converted amount in target currency
            $table->string('currency', 10)->default('USD');

            // Multi-Currency / Gateway Details
            $table->decimal('original_amount', 15, 2)->nullable();
            $table->string('original_currency', 3)->nullable();
            $table->decimal('exchange_rate', 15, 6)->default(1.000000);

            // Flexible status (pending, authorized, captured, processing, failed, refunded, canceled, etc.)
            $table->string('status')->default('pending')->index();

            // Gateway tracking
            $table->decimal('fee', 15, 2)->default(0);
            $table->decimal('net_amount', 15, 2)->default(0);
            $table->string('fee_bearer')->default('customer');

            // Settlement
            $table->string('settlement_status')->nullable(); // pending, settled
            $table->timestamp('settled_at')->nullable();

            // Additional Data
            $table->json('webhook_payload')->nullable();
            $table->string('proof_path')->nullable();
            $table->string('customer_email')->nullable();
            $table->text('notes')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
