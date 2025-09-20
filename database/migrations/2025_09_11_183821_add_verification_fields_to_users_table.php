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
        Schema::table('users', function (Blueprint $table) {
            // Email OTP fields
            $table->string('email_otp')->nullable()->after('email_verified_at');
            $table->timestamp('email_otp_expires_at')->nullable()->after('email_otp');
            $table->timestamp('email_otp_sent_at')->nullable()->after('email_otp_expires_at');

            // WhatsApp OTP fields
            $table->string('whatsapp_otp')->nullable()->after('phone_verified_at');
            $table->timestamp('whatsapp_otp_expires_at')->nullable()->after('whatsapp_otp');

            // Extra user info
            $table->string('id_image_url')->nullable()->after('password');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'email_otp',
                'email_otp_expires_at',
                'email_otp_sent_at',
                'whatsapp_otp',
                'whatsapp_otp_expires_at',
                'id_image_url',
            ]);
        });
    }
};
