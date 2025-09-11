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
        Schema::create('properties', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('owner_id')->nullable();  
            $table->string('title',200);
            $table->text('description');
            $table->enum('type', ['Apartment', 'Villa','Duplex', 'Roof', 'Land']);
            $table->decimal('price', 12, 2);
            $table->enum('price_type', ['FullPay', 'Monthly', 'Daily']);
            $table->json('location');
            $table->integer('size');
            $table->enum('property_state', ['Valid', 'Invalid', 'Pending', 'Rented', 'Sold'])->default('Pending');
            $table->timestamps();
            $table->foreign('owner_id')->references('id')->on('users')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('properties');
    }
};
