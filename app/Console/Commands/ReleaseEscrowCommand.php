<?php

namespace App\Jobs;

use App\Models\PropertyEscrow;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use App\Notifications\EscrowReleasedSeller;
use App\Notifications\EscrowReleasedBuyer;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

class ReleasePropertyEscrow implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected int $escrowId;

    public function __construct(int $escrowId)
    {
        $this->escrowId = $escrowId;
    }

    public function handle()
    {
        DB::transaction(function () {
            $escrow = PropertyEscrow::with(['seller', 'buyer', 'propertyPurchase', 'property'])
                ->find($this->escrowId);

            if (!$escrow) {
                Log::warning("Escrow ID {$this->escrowId} not found, skipping release.");
                return;
            }

            $seller = $escrow->seller;

            if (!$seller) {
                Log::error("Seller missing for escrow ID {$this->escrowId}");
                return;
            }

            Log::info("Releasing escrow ID {$escrow->id} for seller ID {$seller->id}");

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
                'ref_id'         => $escrow->propertyPurchase?->id,
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

            $escrow->propertyPurchase?->update(['status' => 'completed']);

            $escrow->property?->update([
                'property_state'   => 'Sold',
                'pending_buyer_id' => null,
            ]);

            // Send notifications
            Notification::send($escrow->seller, new EscrowReleasedSeller($escrow));
            Notification::send($escrow->buyer, new EscrowReleasedBuyer($escrow));

            Log::info("Escrow ID {$escrow->id} released successfully.");
        });
    }
}