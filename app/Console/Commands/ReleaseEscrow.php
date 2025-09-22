<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\PropertyEscrow;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use App\Enums\NotificationPurpose;

class ReleaseEscrow extends Command
{
    protected $signature = 'escrow:release';
    protected $description = 'Release expired property escrows to sellers';

    public function handle()
    {
        $now = now();

        $escrows = PropertyEscrow::with(['purchase', 'seller'])
            ->where('status', 'locked')
            ->where('scheduled_release_at', '<=', $now)
            ->get();

        foreach ($escrows as $escrow) {
            DB::transaction(function () use ($escrow) {
                try {
                    $seller = $escrow->seller;

                    // Lock seller wallet
                    $wallet = Wallet::lockForUpdate()->firstOrCreate(
                        ['user_id' => $seller->id],
                        ['balance' => 0]
                    );
                    $before = $wallet->balance;
                    $wallet->increment('balance', $escrow->amount);

                    WalletTransaction::create([
                        'wallet_id'      => $wallet->id,
                        'amount'         => $escrow->amount,
                        'type'           => 'sale_income',
                        'ref_id'         => $escrow->purchase->id,
                        'ref_type'       => 'property_purchase',
                        'description'    => 'Escrow released to seller',
                        'balance_before' => $before,
                        'balance_after'  => $wallet->balance,
                    ]);

                    // Update escrow + purchase
                    $escrow->update([
                        'status'         => 'released_to_seller',
                        'released_at'    => now(),
                        'release_reason' => 'buyer_did_not_cancel',
                    ]);

                    $escrow->purchase->update(['status' => 'completed']);

                    // Mark property as sold
                    $escrow->property->update([
                        'status'         => 'sold',
                        'property_state' => 'Invalid',
                        'pending_buyer_id' => null,
                    ]);

                    // Notifications
                    try {
                        $buyerNotification = new \App\Notifications\EscrowReleasedBuyer($escrow->purchase);
                        Notification::send($escrow->buyer, $buyerNotification);

                        $sellerNotification = new \App\Notifications\EscrowReleasedSeller($escrow->purchase);
                        Notification::send($escrow->seller, $sellerNotification);
                    } catch (\Exception $e) {
                        Log::warning('Escrow notification failed', ['error' => $e->getMessage()]);
                    }

                    $this->info("Released escrow #{$escrow->id} to seller {$seller->id}");
                } catch (\Throwable $e) {
                    Log::error("Failed to release escrow {$escrow->id}: " . $e->getMessage());
                }
            });
        }
    }
}