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
use Illuminate\Support\Facades\Notification;

class ReleasePropertyEscrows implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $escrow;

    public function __construct(PropertyEscrow $escrow)
    {
        $this->escrow = $escrow;
    }

    public function handle()
    {
        DB::transaction(function () {
            $escrow = PropertyEscrow::with(['buyer','seller','property'])->findOrFail($this->escrow->id);

            if($escrow->status !== 'locked') return;

            $seller = $escrow->seller;

            $wallet = Wallet::firstOrCreate(
                ['user_id' => $seller->id],
                ['balance' => 0]
            );

            $before = $wallet->balance;
            $wallet->increment('balance', $escrow->amount);

            WalletTransaction::create([
                'wallet_id' => $wallet->id,
                'amount' => $escrow->amount,
                'type' => 'sale_income',
                'ref_id' => $escrow->id,
                'ref_type' => 'property_escrow',
                'description' => 'Escrow released to seller',
                'balance_before' => $before,
                'balance_after' => $wallet->balance,
            ]);

            $escrow->update([
                'status' => 'released_to_seller',
                'released_at' => now(),
                'release_reason' => 'auto_release',
            ]);

            Notification::send($escrow->seller, new EscrowReleasedSeller($escrow));
            Notification::send($escrow->buyer, new EscrowReleasedBuyer($escrow));
        });
    }
}