<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Step 1: Add owner_id column as nullable first
        Schema::table('escrow_balances', function (Blueprint $table) {
            $table->unsignedBigInteger('owner_id')->nullable()->after('user_id');
        });

        // Step 2: Update existing records to set owner_id
        // Get owner_id from rent_requests -> properties relationship
        DB::statement("
            UPDATE escrow_balances eb
            JOIN rent_requests rr ON eb.rent_request_id = rr.id
            JOIN properties p ON rr.property_id = p.id
            SET eb.owner_id = p.owner_id
        ");

        // Step 3: Make owner_id NOT NULL and add constraints
        Schema::table('escrow_balances', function (Blueprint $table) {
            // Make column NOT NULL
            $table->unsignedBigInteger('owner_id')->nullable(false)->change();
            
            // Add foreign key constraint
            $table->foreign('owner_id')->references('id')->on('users')->onDelete('cascade');
            
            // Add index for performance
            $table->index('owner_id');
        });

        // Step 4: Update the status enum to be more explicit
        // First update existing 'released' records to 'released_to_owner'
        DB::table('escrow_balances')
            ->where('status', 'released')
            ->update(['status' => 'released_to_owner']);

        // Then change the enum definition
        Schema::table('escrow_balances', function (Blueprint $table) {
            $table->enum('status', [
                'locked', 
                'released_to_owner', 
                'released_to_renter'
            ])->default('locked')->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('escrow_balances', function (Blueprint $table) {
            // Drop foreign key and index first
            $table->dropForeign(['owner_id']);
            $table->dropIndex(['owner_id']);
            
            // Drop the column
            $table->dropColumn('owner_id');
        });

        // Revert status enum back to original
        Schema::table('escrow_balances', function (Blueprint $table) {
            $table->enum('status', ['locked', 'released'])->default('locked')->change();
        });
    }
};