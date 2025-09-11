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
        Schema::table('property_images', function (Blueprint $table) {
            // Check if columns exist before adding them
            if (!Schema::hasColumn('property_images', 'public_id')) {
                $table->string('public_id')->nullable()->after('image_url');
            }
            if (!Schema::hasColumn('property_images', 'original_filename')) {
                $table->string('original_filename')->nullable()->after('description');
            }
            if (!Schema::hasColumn('property_images', 'size')) {
                $table->integer('size')->nullable()->after('original_filename');
            }
            if (!Schema::hasColumn('property_images', 'width')) {
                $table->integer('width')->nullable()->after('size');
            }
            if (!Schema::hasColumn('property_images', 'height')) {
                $table->integer('height')->nullable()->after('width');
            }
            
            // Make image_type nullable if it exists
            if (!Schema::hasColumn('property_images', 'image_type')) {
                $table->string('image_type')->nullable();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('property_images', function (Blueprint $table) {
            $table->dropColumn(['public_id', 'original_filename', 'size', 'width', 'height']);
        });
    }
};
