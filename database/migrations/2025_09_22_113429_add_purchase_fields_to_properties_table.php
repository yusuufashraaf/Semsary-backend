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
        Schema::table('properties', function (Blueprint $table) {
            // Add pending buyer field (nullable FK to users)
            $table->unsignedBigInteger('pending_buyer_id')->nullable()->after('owner_id');
            
            // Add transfer scheduled timestamp (nullable to avoid MySQL default issues)
            $table->timestamp('transfer_scheduled_at')->nullable()->after('updated_at');

            // Add foreign key constraint
            $table->foreign('pending_buyer_id')
                  ->references('id')
                  ->on('users')
                  ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('properties', function (Blueprint $table) {
            // Drop foreign key first
            $table->dropForeign(['pending_buyer_id']);

            // Drop the columns
            $table->dropColumn(['pending_buyer_id', 'transfer_scheduled_at']);
        });
    }
};