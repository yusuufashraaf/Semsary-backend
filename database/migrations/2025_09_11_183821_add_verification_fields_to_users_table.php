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



            // --- Phone/WhatsApp Verification Fields ---

            // A timestamp to mark when the phone number was verified.
            // The 6-digit OTP code for WhatsApp/SMS.
            // The expiration timestamp for the WhatsApp OTP.

            $table->string('whatsapp_otp')->nullable()->after('phone_verified_at');

            $table->timestamp('whatsapp_otp_expires_at')->nullable()->after('whatsapp_otp');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Drop columns in the reverse order they were added.
            $table->dropColumn([
            'whatsapp_otp',
            'whatsapp_otp_expires_at',
            ]);
        });
    }
};
