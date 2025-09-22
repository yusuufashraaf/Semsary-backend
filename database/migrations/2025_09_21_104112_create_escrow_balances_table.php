<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('escrow_balances', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('rent_request_id')->unique();
            $table->unsignedBigInteger('user_id'); // renter who paid

            $table->decimal('rent_amount', 12, 2)->default(0);
            $table->decimal('deposit_amount', 12, 2)->default(0);
            $table->decimal('total_amount', 12, 2)->default(0); // rent + deposit

            // Fixed status enum to match controller expectations
            $table->enum('status', ['locked', 'released'])->default('locked');

            $table->timestamp('locked_at')->nullable();
            $table->timestamp('released_at')->nullable();
            
            $table->timestamps();

            // Foreign keys
            $table->foreign('rent_request_id')->references('id')->on('rent_requests')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');

            // Indexes
            $table->index('user_id');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('escrow_balances');
    }
};