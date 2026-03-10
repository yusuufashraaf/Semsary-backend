<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchases', function (Blueprint $table) {
            $table->enum('payment_gateway', [
                'Wallet',
                'Stripe',
                'PayPal',
                'Fawry',
                'PayMob',
                'Flutterwave'
            ])->change();
        });
    }

    public function down(): void
    {
        Schema::table('purchases', function (Blueprint $table) {
            // Rollback to original (you can adjust as needed)
            $table->string('payment_gateway')->change();
        });
    }
};