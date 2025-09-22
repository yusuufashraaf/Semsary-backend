<?php

// database/migrations/2024_01_01_000005_create_checkouts_table.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('checkouts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('rent_request_id')->unique();
            $table->unsignedBigInteger('requester_id');
            
            $table->timestamp('requested_at');
            $table->enum('status', [
                'pending',
                'auto_confirmed',
                'confirmed',
                'rejected'
            ])->default('pending');
            
            $table->enum('type', [
                'before_checkin',
                'within_1_day',
                'after_1_day',
                'monthly_mid_contract'
            ]);

            $table->text('reason')->nullable();
            
            $table->decimal('deposit_return_percent', 5, 2)->nullable();
            $table->text('agent_notes')->nullable();
            $table->json('agent_decision')->nullable();
            
            $table->enum('owner_confirmation', [
                'pending',
                'confirmed',
                'rejected',
                'not_required',
                'auto_confirmed'
            ])->default('pending');
            
            $table->text('owner_notes')->nullable();
            $table->timestamp('owner_confirmed_at')->nullable();
            
            $table->text('admin_note')->nullable();
            
            // Financial references
            $table->unsignedBigInteger('refund_purchase_id')->nullable();
            $table->unsignedBigInteger('payout_purchase_id')->nullable();
            $table->string('transaction_ref')->nullable();
            $table->decimal('final_refund_amount', 12, 2)->nullable();
            $table->decimal('final_payout_amount', 12, 2)->nullable();
            
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            // Foreign keys
            $table->foreign('rent_request_id')->references('id')->on('rent_requests')->onDelete('cascade');
            $table->foreign('requester_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('refund_purchase_id')->references('id')->on('purchases')->onDelete('set null');
            $table->foreign('payout_purchase_id')->references('id')->on('purchases')->onDelete('set null');

            // Indexes
            $table->index('status');
            $table->index('type');
            $table->index('owner_confirmation');
            $table->index('requested_at');
            $table->index('processed_at');
            $table->index('transaction_ref');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('checkouts');
    }
};