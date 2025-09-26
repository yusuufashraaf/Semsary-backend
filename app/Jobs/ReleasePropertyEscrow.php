<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\PropertyEscrow;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use App\Notifications\EscrowReleasedSeller;
use App\Notifications\EscrowReleasedBuyer;

class ReleaseEscrows extends Command
{
    protected $signature = 'escrow:release';
    protected $description = 'Release locked property escrows after 2 Minutes if not cancelled';

    public function handle()
    {
        $now = now();
        $escrows = PropertyEscrow::with('purchase', 'purchase.property', 'purchase.seller', 'purchase.buyer')
            ->where('status', 'locked')
->where('created_at', '<=', now()->subMinutes(2))
            ->get();

        foreach ($escrows as $escrow) {
            DB::transaction(function () use ($escrow, &$releasedEscrows) {
                // Re-check status inside transaction to prevent double release
                $escrow->refresh();
                if ($escrow->status !== 'locked') {
                    return;
                }

                $purchase = $escrow->purchase;
                $property = $purchase->property;
                $seller   = $purchase->seller;

                // 1. Credit seller’s wallet
                $wallet = Wallet::lockForUpdate()->firstOrCreate(
                    ['user_id' => $seller->id],
                    ['balance' => 0]
                );

                $before = $wallet->balance;
                $wallet->increment('balance', $escrow->amount);

                WalletTransaction::create([
                    'wallet_id'       => $wallet->id,
                    'amount'          => $escrow->amount,
                    'type'            => 'sale_income',
                    'ref_id'          => $purchase->id,
                    'ref_type'        => 'property_purchase',
                    'description'     => 'Escrow released to seller after 2 minutes',
                    'balance_before'  => $before,
                    'balance_after'   => $wallet->balance,
                ]);

                // 2. Transfer ownership to buyer
                $property->update([
                    'owner_id'        => $purchase->buyer_id,
                    'status'          => 'sold',
                    'property_state'  => 'Valid',
                    'pending_buyer_id'=> null,
                ]);

                // 3. Update purchase & escrow
                $purchase->update(['status' => 'completed']);
                $escrow->update([
                    'status'         => 'released_to_seller',
                    'released_at'    => now(),
                    'release_reason' => 'auto_release_after_2M'
                ]);
            });

            //  Notifications after commit
            try {
                $escrow->refresh(); // ensure latest relations
                Notification::send($escrow->purchase->seller, new EscrowReleasedSeller($escrow));
                Notification::send($escrow->purchase->buyer, new EscrowReleasedBuyer($escrow));
            } catch (\Throwable $e) {
                Log::warning('Escrow release notifications failed', [
                    'escrow_id' => $escrow->id,
                    'error'     => $e->getMessage()
                ]);
            }
        }

        $this->info('Escrow release job executed.');
    }
}