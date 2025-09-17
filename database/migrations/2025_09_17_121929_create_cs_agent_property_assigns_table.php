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
        Schema::create('cs_agent_property_assigns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('property_id')->constrained()->onDelete('cascade');
            $table->foreignId('cs_agent_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('assigned_by')->constrained('users'); // Admin who assigned
            $table->enum('status', ['pending', 'in_progress', 'completed', 'rejected'])->default('pending');
            $table->text('notes')->nullable();
            $table->json('metadata')->nullable(); // For additional data like priority, tags, etc.
            $table->timestamp('assigned_at');
            $table->timestamp('started_at')->nullable(); // When agent started working
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            // Indexes for performance
            $table->index(['cs_agent_id', 'status'], 'cs_agent_status_idx');
            $table->index(['property_id', 'status'], 'property_status_idx');
            $table->index('assigned_at');
            $table->index(['assigned_by', 'created_at'], 'assigned_by_date_idx');
        });

        // Add indexes to existing tables for better performance with CS agent queries
        Schema::table('users', function (Blueprint $table) {
            // Only add if doesn't exist
            if (!Schema::hasIndex('users', 'role_status_idx')) {
                $table->index(['role', 'status'], 'role_status_idx');
            }
        });

        Schema::table('properties', function (Blueprint $table) {
            // Only add if doesn't exist
            if (!Schema::hasIndex('properties', 'property_state_created_idx')) {
                $table->index(['property_state', 'created_at'], 'property_state_created_idx');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cs_agent_property_assigns');

        // Drop the indexes we added
        Schema::table('users', function (Blueprint $table) {
            $sm = Schema::getConnection()->getDoctrineSchemaManager();
            $indexes = $sm->listTableIndexes('users');

            if (array_key_exists('role_status_idx', $indexes)) {
                $table->dropIndex('role_status_idx');
            }
        });

        Schema::table('properties', function (Blueprint $table) {
            $sm = Schema::getConnection()->getDoctrineSchemaManager();
            $indexes = $sm->listTableIndexes('properties');

            if (array_key_exists('property_state_created_idx', $indexes)) {
                $table->dropIndex('property_state_created_idx');
            }
        });
    }
};
