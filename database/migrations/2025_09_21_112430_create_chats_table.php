<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('chats', function (Blueprint $table) {
            $table->id();
            $table->foreignId('property_id')->constrained()->onDelete('cascade');
            $table->foreignId('owner_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('renter_id')->constrained('users')->onDelete('cascade');
            $table->timestamp('last_message_at')->nullable();
            $table->timestamps();
            
            // UNIQUE: One chat per property-owner combination
            // This allows multiple renters for the same property with same owner
            // and same renter to have multiple properties with same owner
            $table->unique(['property_id', 'owner_id']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('chats');
    }
};