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
        Schema::create('property_images', function (Blueprint $table) {
            $table->id();
            $table->foreignId('property_id')->constrained()->onDelete('cascade');
            $table->string('image_url');
            $table->integer('order_index')->default(0);
            $table->string('description')->nullable();
            $table->string('public_id')->nullable()->change();
            $table->string('image_type')->nullable()->change();
            $table->string('original_filename')->nullable()->change();
            $table->integer('size')->nullable()->change();
            $table->integer('width')->nullable()->change();
            $table->integer('height')->nullable()->change();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('property_images');
    }
};
