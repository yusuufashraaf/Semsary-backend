<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('purchases', function (Blueprint $table) {
            // Drop unique and normal index on transaction_ref
            $table->dropUnique('purchases_transaction_id_unique');
            $table->dropIndex('purchases_transaction_id_index');

            // Make idempotency_key unique
            $table->unique('idempotency_key', 'purchases_idempotency_key_unique');
        });
    }

    public function down(): void
    {
        Schema::table('purchases', function (Blueprint $table) {
            // Re-add transaction_ref unique + index
            $table->unique('transaction_ref', 'purchases_transaction_id_unique');
            $table->index('transaction_ref', 'purchases_transaction_id_index');

            // Drop unique on idempotency_key
            $table->dropUnique('purchases_idempotency_key_unique');
        });
    }
};