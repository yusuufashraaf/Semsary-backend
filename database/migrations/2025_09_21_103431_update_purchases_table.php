<?php

// database/migrations/2024_01_01_000006_update_existing_purchases_table.php
// Use this if you already have a purchases table that needs updating
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchases', function (Blueprint $table) {
            // Add new columns if they don't exist
            if (!Schema::hasColumn('purchases', 'rent_request_id')) {
                $table->unsignedBigInteger('rent_request_id')->nullable()->after('property_id');
                $table->foreign('rent_request_id')->references('id')->on('rent_requests')->onDelete('cascade');
            }

            if (!Schema::hasColumn('purchases', 'payment_type')) {
                $table->enum('payment_type', [
                    'rent',
                    'deposit',
                    'refund',
                    'payout',
                    'full_payment'
                ])->default('full_payment')->after('deposit_amount');
            }

            // Update status enum if it exists with wrong values
            if (Schema::hasColumn('purchases', 'status')) {
                DB::statement("ALTER TABLE purchases MODIFY status ENUM('pending','successful','failed','refunded') DEFAULT 'pending'");
            }

            // Rename transaction_id to transaction_ref if needed
            if (Schema::hasColumn('purchases', 'transaction_id') && !Schema::hasColumn('purchases', 'transaction_ref')) {
                $table->renameColumn('transaction_id', 'transaction_ref');
            }

            if (!Schema::hasColumn('purchases', 'idempotency_key')) {
                $table->string('idempotency_key')->nullable()->after('transaction_ref');
                $table->index('idempotency_key');
            }

            if (!Schema::hasColumn('purchases', 'metadata')) {
                $table->json('metadata')->nullable()->after('payment_details');
            }

            // Add Wallet to payment_gateway enum if not present
            DB::statement("ALTER TABLE purchases MODIFY payment_gateway ENUM('PayMob','PayPal','Fawry','Stripe','Wallet')");
        });
    }

    public function down(): void
    {
        Schema::table('purchases', function (Blueprint $table) {
            if (Schema::hasColumn('purchases', 'rent_request_id')) {
                $table->dropForeign(['rent_request_id']);
                $table->dropColumn('rent_request_id');
            }
            if (Schema::hasColumn('purchases', 'payment_type')) {
                $table->dropColumn('payment_type');
            }
            if (Schema::hasColumn('purchases', 'idempotency_key')) {
                $table->dropColumn('idempotency_key');
            }
            if (Schema::hasColumn('purchases', 'metadata')) {
                $table->dropColumn('metadata');
            }
            if (Schema::hasColumn('purchases', 'transaction_ref')) {
                $table->renameColumn('transaction_ref', 'transaction_id');
            }
        });
    }
};