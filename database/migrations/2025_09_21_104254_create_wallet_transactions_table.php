<?php

// database/migrations/2024_01_01_000004_create_wallet_transactions_table.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wallet_transactions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('wallet_id');
            $table->decimal('amount', 12, 2); // Can be positive or negative
            
            $table->enum('type', [
                'payment',     // Deduction for payment
                'refund',      // Credit from refund
                'payout',      // Credit from rental payout
                'withdrawal',  // Deduction for withdrawal
                'deposit',     // Manual deposit
                'adjustment'   // Admin adjustment
            ]);
            
            $table->unsignedBigInteger('ref_id')->nullable(); // Reference to related record
            $table->string('ref_type')->nullable(); // Type of reference (checkout, purchase, etc.)
            $table->string('description')->nullable();
            $table->decimal('balance_before', 12, 2)->nullable();
            $table->decimal('balance_after', 12, 2)->nullable();
            
            $table->timestamps();

            // Foreign keys
            $table->foreign('wallet_id')->references('id')->on('wallets')->onDelete('cascade');

            // Indexes
            $table->index('wallet_id');
            $table->index('type');
            $table->index(['ref_id', 'ref_type']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wallet_transactions');
    }
};