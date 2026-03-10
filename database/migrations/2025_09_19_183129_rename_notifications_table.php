<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // Rename the table
        Schema::rename('notifications', 'user_notifications');
    }

    public function down(): void
    {
        // Rollback rename
        Schema::rename('user_notifications', 'notifications');
    }
};