<?php

namespace App\Jobs;

use App\Models\EscrowBalance;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use App\Models\Property;
use App\Models\RentRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class ReleaseRentJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $rentRequestId;

    public function __construct(int $rentRequestId)
    {
        $this->rentRequestId = $rentRequestId;
    }

    public function handle(): void
    {
        DB::transaction(function () {
            $rentRequest = RentRequest::with('property')
                ->lockForUpdate()
                ->find($this->rentRequestId);

            if (!$rentRequest) {
                Log::warning("ReleaseRentJob: RentRequest not found", ['id' => $this->rentRequestId]);
                return;
            }

            $escrow = EscrowBalance::where('rent_request_id', $rentRequest->id)
                ->lockForUpdate()
                ->first();

            if (!$escrow || $escrow->rent_released) {
                Log::info("ReleaseRentJob: Rent already released or no escrow found", ['id' => $this->rentRequestId]);
                return;
            }

            $ownerId = $rentRequest->property->owner_id;
            $rentAmount = $escrow->rent_amount;

            // credit owner’s wallet
            $wallet = Wallet::firstOrCreate(
                ['user_id' => $ownerId],
                ['balance' => 0]
            );

            $wallet->increment('balance', $rentAmount);

            // log transaction
// log transaction
WalletTransaction::create([
    'wallet_id' => $wallet->id,
    'amount' => $rentAmount,
    'type' => 'payout', //  use allowed enum
    'description' => "Rent released for rent_request #{$rentRequest->id}",
    'ref_id' => $rentRequest->id,
    'ref_type' => 'rent_request',
    'balance_before' => $wallet->balance - $rentAmount,
    'balance_after' => $wallet->balance,
]);

            // mark escrow as released (only rent, deposit stays locked)
            $escrow->update([
                'rent_released' => true,
                'rent_released_at' => Carbon::now(),
            ]);

            Log::info("ReleaseRentJob: Rent released successfully", [
                'rent_request_id' => $rentRequest->id,
                'owner_id' => $ownerId,
                'amount' => $rentAmount,
            ]);
        });
    }
}