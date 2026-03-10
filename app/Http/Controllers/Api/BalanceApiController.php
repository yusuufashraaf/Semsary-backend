<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PropertyEscrow;
use App\Models\Wallet;
use App\Models\EscrowBalance;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class BalanceApiController extends Controller
{
    public function show(Request $request)
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'You must be logged in to view balances.',
            ], 401);
        }

        // Wallet balance (immediately available)
        $walletBalance = Wallet::where('user_id', $user->id)->value('balance') ?? 0;

        // LOCKED MONEY (can be refunded but still frozen)
        $locked = 0;
        
        // Property escrows - money as buyer that's locked
        $locked += PropertyEscrow::where('buyer_id', $user->id)
            ->where('status', 'locked')
            ->sum('amount');

        // Rent escrows - money as renter that's locked
        $locked += EscrowBalance::where('user_id', $user->id)
            ->where('status', 'locked')
            ->sum('total_amount');

        // CLAIMABLE MONEY (released to me, ready to withdraw)
        $claimable = 0;

        // Property escrows - money released to me as seller
        $claimable += PropertyEscrow::where('seller_id', $user->id)
            ->where('status', 'released_to_seller')
            ->sum('amount');

        // Property escrows - money released back to me as buyer (refunds)
        $claimable += PropertyEscrow::where('buyer_id', $user->id)
            ->where('status', 'released_to_buyer')
            ->sum('amount');

        // Rent escrows - money released to me as owner
        $claimable += EscrowBalance::where('owner_id', $user->id)
            ->where('status', 'released_to_owner')
            ->sum('total_amount');

        // Rent escrows - money released back to me as renter (refunds)
        $claimable += EscrowBalance::where('user_id', $user->id)
            ->where('status', 'released_to_renter')
            ->sum('total_amount');

        // TOTALS
        $availableNow = $walletBalance + $claimable;
        $totalInSystem = $walletBalance + $locked + $claimable;

        return response()->json([
            "success"       => true,
            "wallet"        => (float) $walletBalance,      // Money in wallet
            "locked"        => (float) $locked,             // Money in escrow (locked)
            "claimable"     => (float) $claimable,          // Money released to me (ready to claim)
            "available_now" => (float) $availableNow,       // Wallet + claimable
            "total_in_app"  => (float) $totalInSystem,      // Everything I have access to
        ]);
    }
}