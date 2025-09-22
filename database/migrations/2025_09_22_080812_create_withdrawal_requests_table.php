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
        Schema::create('withdrawal_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->decimal('amount', 10, 2);
            $table->enum('gateway', ['paymob', 'paypal']);
            $table->json('account_details'); 
            $table->enum('status', ['processing', 'completed', 'failed'])->default('processing');
            $table->string('transaction_ref')->unique();
            $table->timestamp('requested_at');
            $table->timestamp('completed_at')->nullable();
            $table->json('gateway_response')->nullable(); 
            $table->timestamps();
            
            // Indexes for performance
            $table->index(['user_id', 'status']);
            $table->index(['created_at']);
            $table->index('transaction_ref');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('withdrawal_requests');
    }
};