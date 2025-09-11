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
        Schema::table('property_documents', function (Blueprint $table) {
                // Add document_type if it doesn't exist
                if (!Schema::hasColumn('property_documents', 'document_type')) {
                    $table->string('document_type')->nullable()->after('document_url');
                }
                
                // Add public_id if it doesn't exist
                if (!Schema::hasColumn('property_documents', 'public_id')) {
                    $table->string('public_id')->nullable()->after('document_url');
                }
                
                // Add original_filename if it doesn't exist
                if (!Schema::hasColumn('property_documents', 'original_filename')) {
                    $table->string('original_filename')->nullable();
                }
                
                // Add size if it doesn't exist
                if (!Schema::hasColumn('property_documents', 'size')) {
                    $table->integer('size')->nullable();
                }
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
