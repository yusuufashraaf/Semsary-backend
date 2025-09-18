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
        // Add CS_Agent to the role enum
        DB::statement("ALTER TABLE users MODIFY COLUMN role ENUM('user', 'owner', 'agent', 'admin', 'CS_Agent') DEFAULT 'user'");

        // Optional: Update existing 'agent' users to 'CS_Agent' if needed
        // Uncomment the line below if you want to migrate existing agents to CS_Agent
        // DB::table('users')->where('role', 'agent')->update(['role' => 'CS_Agent']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Revert CS_Agent users back to agent (if any exist)
        DB::table('users')->where('role', 'CS_Agent')->update(['role' => 'agent']);

        // Remove CS_Agent from the role enum
        DB::statement("ALTER TABLE users MODIFY COLUMN role ENUM('user', 'owner', 'agent', 'admin') DEFAULT 'user'");
    }
};
