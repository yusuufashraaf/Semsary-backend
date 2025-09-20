<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('rent_requests', function (Blueprint $table) {
            $table->id();

            // Foreign keys
            $table->foreignId('user_id')
                ->constrained('users')
                ->cascadeOnDelete();

            $table->foreignId('property_id')
                ->constrained('properties')
                ->cascadeOnDelete();

            // Booking dates
            $table->date('check_in');
            $table->date('check_out');

            // Status lifecycle
            $table->enum('status', [
                'pending',            // renter submitted, waiting for owner
                'cancelled',          // cancelled by user before owner confirmed
                'rejected',           // rejected by owner
                'confirmed',          // approved by owner, awaiting payment
                'cancelled_by_owner', // owner cancelled before renter paid
                'paid',               // renter paid, booking locked
                'completed'           // rental finished
            ])->default('pending');

            // Control fields
            $table->dateTime('blocked_until')->nullable();
            $table->dateTime('payment_deadline')->nullable();
            $table->dateTime('cooldown_expires_at')->nullable();

            // Timestamps
            $table->timestamps();

            // Indexes for performance
            $table->index('user_id');
            $table->index('property_id');
            $table->index(['status', 'payment_deadline']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rent_requests');
    }
};