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
        Schema::create('payment_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payment_id')->constrained()->onDelete('cascade');

            $table->string('external_id')->nullable()->index(); // ID from gateway for this specific attempt
            $table->enum('status', ['pending','processing','paid','failed','cancelled','expired','refunded','partially_refunded'])->default('pending');

            // Financials of this attempt
            $table->decimal('amount', 15, 2);
            $table->string('currency', 10);

            $table->json('payload')->nullable(); // Store the raw gateway response for this attempt
            $table->string('error_code')->nullable();
            $table->text('error_message')->nullable();

            $table->unique(['external_id', 'payment_id']);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payment_transactions');
    }
};
