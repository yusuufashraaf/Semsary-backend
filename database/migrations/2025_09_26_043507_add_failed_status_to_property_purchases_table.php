<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Update the enum to include 'failed' status
        DB::statement("ALTER TABLE property_purchases MODIFY COLUMN status ENUM('pending', 'paid', 'completed', 'cancelled', 'refunded', 'failed')");
    }

    public function down(): void
    {
        // Remove 'failed' status from enum
        DB::statement("ALTER TABLE property_purchases MODIFY COLUMN status ENUM('pending', 'paid', 'completed', 'cancelled', 'refunded')");
    }
};