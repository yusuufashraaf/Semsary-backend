<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('notifications', function (Blueprint $table) {
            $table->string('purpose')->nullable()->after('user_id');
            $table->unsignedBigInteger('sender_id')->nullable()->after('purpose');
            $table->unsignedBigInteger('entity_id')->nullable()->after('sender_id');

            $table->foreign('sender_id')
                ->references('id')
                ->on('users')
                ->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('notifications', function (Blueprint $table) {
            $table->dropForeign(['sender_id']);
            $table->dropColumn(['purpose', 'sender_id', 'entity_id']);
        });
    }
};