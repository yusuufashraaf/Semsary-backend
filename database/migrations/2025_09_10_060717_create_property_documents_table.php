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
        Schema::create('property_documents', function (Blueprint $table) {
           $table->id();
            $table->foreignId('property_id')->constrained()->onDelete('cascade');
            $table->string('document_url');
            $table->string('public_id')->nullable()->change();
            $table->string('document_type')->nullable()->change();
            $table->string('original_filename')->nullable()->change();
            $table->integer('size')->nullable()->change();
            $table->timestamps();
                });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('property_documents');
    }
};
