<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\PropertyEscrow;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ReleaseEscrow extends Command
{
    protected $signature = 'escrow:release';
    protected $description = 'Release expired property escrows to sellers';

    public function handle()
    {
        $now = now();

        $escrows = PropertyEscrow::with(['propertyPurchase', 'seller', 'buyer', 'property'])
            ->where('status', 'locked')
            ->where('scheduled_release_at', '<=', $now)
            ->get();

        if ($escrows->isEmpty()) {
            $this->info("No escrows ready for release at {$now}");
            return;
        }

        $this->info("Found {$escrows->count()} escrows ready for release.");

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
                        'ref_id'         => $escrow->propertyPurchase->id,
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

                    $escrow->propertyPurchase->update(['status' => 'completed']);

                    // Mark property as sold
                    $escrow->property->update([
                        'property_state'   => 'Sold',
                        'pending_buyer_id' => null,
                    ]);

                    $this->info("Released escrow #{$escrow->id} to seller #{$seller->id}");
                } catch (\Throwable $e) {
                    Log::error("Failed to release escrow {$escrow->id}: " . $e->getMessage());
                    $this->error("Failed to release escrow #{$escrow->id}");
                }
            });
        }
    }
}