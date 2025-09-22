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
        Schema::create('property_purchases', function (Blueprint $table) {
            $table->id(); // Standard auto-increment id
            $table->foreignId('property_id')->constrained()->onDelete('cascade');
            $table->foreignId('buyer_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('seller_id')->constrained('users')->onDelete('cascade');
            $table->decimal('amount', 12, 2);
            $table->enum('status', ['pending', 'paid', 'completed', 'cancelled', 'refunded']);
            $table->string('payment_gateway')->nullable();
            $table->string('transaction_ref')->nullable();
            $table->string('idempotency_key')->unique();
$table->timestamp('purchase_date')->nullable();
$table->timestamp('cancellation_deadline')->nullable();
            $table->timestamp('completion_date')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('property_purchases');
    }
};