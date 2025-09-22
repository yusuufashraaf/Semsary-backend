<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        // get all indexes on purchases
        $indexes = DB::select("SHOW INDEX FROM purchases");
        $indexNames = collect($indexes)->pluck('Key_name')->toArray();

        Schema::table('purchases', function (Blueprint $table) use ($indexNames) {
            // drop unique index if it exists
            if (in_array('purchases_idempotency_key_unique', $indexNames)) {
                $table->dropUnique('purchases_idempotency_key_unique');
            }

            // drop normal index if it exists
            if (in_array('purchases_idempotency_key_index', $indexNames)) {
                $table->dropIndex('purchases_idempotency_key_index');
            }

            // now add the correct normal index
            $table->index('idempotency_key', 'purchases_idempotency_key_index');
        });
    }

    public function down(): void
    {
        $indexes = DB::select("SHOW INDEX FROM purchases");
        $indexNames = collect($indexes)->pluck('Key_name')->toArray();

        Schema::table('purchases', function (Blueprint $table) use ($indexNames) {
            // drop normal index if it exists
            if (in_array('purchases_idempotency_key_index', $indexNames)) {
                $table->dropIndex('purchases_idempotency_key_index');
            }

            // restore unique index
            if (!in_array('purchases_idempotency_key_unique', $indexNames)) {
                $table->unique('idempotency_key', 'purchases_idempotency_key_unique');
            }
        });
    }
};