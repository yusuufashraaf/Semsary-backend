<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        $indexes = DB::select("SHOW INDEX FROM purchases");
        $indexNames = collect($indexes)->pluck('Key_name')->toArray();

        Schema::table('purchases', function (Blueprint $table) use ($indexNames) {
            // Drop old transaction indexes if exist
            if (in_array('purchases_transaction_id_unique', $indexNames)) {
                $table->dropUnique('purchases_transaction_id_unique');
            }
            if (in_array('purchases_transaction_id_index', $indexNames)) {
                $table->dropIndex('purchases_transaction_id_index');
            }

            // Drop unique if still exists on idempotency_key
            if (in_array('purchases_idempotency_key_unique', $indexNames)) {
                $table->dropUnique('purchases_idempotency_key_unique');
            }

            // Ensure it's just a normal index
            if (!in_array('purchases_idempotency_key_index', $indexNames)) {
                $table->index('idempotency_key', 'purchases_idempotency_key_index');
            }
        });
    }

    public function down(): void
    {
        $indexes = DB::select("SHOW INDEX FROM purchases");
        $indexNames = collect($indexes)->pluck('Key_name')->toArray();

        Schema::table('purchases', function (Blueprint $table) use ($indexNames) {
            // Re-add transaction_ref unique + index
            if (!in_array('purchases_transaction_id_unique', $indexNames)) {
                $table->unique('transaction_ref', 'purchases_transaction_id_unique');
            }
            if (!in_array('purchases_transaction_id_index', $indexNames)) {
                $table->index('transaction_ref', 'purchases_transaction_id_index');
            }

            // Optional: revert idempotency_key back to unique
            if (in_array('purchases_idempotency_key_index', $indexNames)) {
                $table->dropIndex('purchases_idempotency_key_index');
            }
            if (!in_array('purchases_idempotency_key_unique', $indexNames)) {
                $table->unique('idempotency_key', 'purchases_idempotency_key_unique');
            }
        });
    }
};