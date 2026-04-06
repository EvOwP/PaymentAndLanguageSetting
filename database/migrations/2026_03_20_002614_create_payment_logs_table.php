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
            $table->string('event_id')->nullable();
            $table->foreignId('payment_id')->constrained()->onDelete('cascade');
            $table->string('event_type');
            $table->json('payload')->nullable();
            $table->ipAddress('ip_address')->nullable();
            $table->boolean('is_verified')->default(false);
            $table->text('signature')->nullable();
            $table->boolean('processed')->default(false);
            $table->integer('retry_count')->default(0);
            $table->timestamp('processed_at')->nullable();

            $table->unique(['event_id', 'payment_id']);
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
