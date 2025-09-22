<?php

// database/migrations/2024_01_01_000007_add_checkout_relationships_to_rent_requests.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rent_requests', function (Blueprint $table) {
            // Add any missing fields needed for checkout system
            if (!Schema::hasColumn('rent_requests', 'payment_deadline')) {
                $table->timestamp('payment_deadline')->nullable()->after('status');
            }
            
            // Add checkout-related status if not present
            $currentStatuses = DB::select("SHOW COLUMNS FROM rent_requests WHERE Field = 'status'");
            if (!empty($currentStatuses)) {
                $statusEnum = $currentStatuses[0]->Type;
                if (!str_contains($statusEnum, 'completed')) {
                    DB::statement("ALTER TABLE rent_requests MODIFY status ENUM('pending','confirmed','paid','cancelled','rejected','cancelled_by_owner','completed') DEFAULT 'pending'");
                }
            }
        });
    }

    public function down(): void
    {
        // Don't remove existing columns in down migration to avoid data loss
    }
};