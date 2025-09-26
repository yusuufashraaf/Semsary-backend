<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\PropertyEscrow;
use App\Jobs\ReleasePropertyEscrows;
use Illuminate\Support\Facades\DB;

class ReleaseEscrowCommands extends Command
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