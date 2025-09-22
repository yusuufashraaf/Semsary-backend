<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('escrow_balances', function (Blueprint $table) {
            if (!Schema::hasColumn('escrow_balances', 'rent_released')) {
                $table->boolean('rent_released')->default(false)->after('released_at');
            }
            if (!Schema::hasColumn('escrow_balances', 'rent_released_at')) {
                $table->timestamp('rent_released_at')->nullable()->after('rent_released');
            }
        });
    }

    public function down(): void
    {
        Schema::table('escrow_balances', function (Blueprint $table) {
            if (Schema::hasColumn('escrow_balances', 'rent_released')) {
                $table->dropColumn('rent_released');
            }
            if (Schema::hasColumn('escrow_balances', 'rent_released_at')) {
                $table->dropColumn('rent_released_at');
            }
        });
    }
};