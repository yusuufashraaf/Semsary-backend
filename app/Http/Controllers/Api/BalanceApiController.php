<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PropertyEscrow;
use App\Models\Wallet;
use App\Models\EscrowBalance;
use Illuminate\Http\Request;

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

        // Wallet balance
        $walletBalance = Wallet::where('user_id', $user->id)->value('balance') ?? 0;

        // Locked (escrow still frozen)
        $locked = PropertyEscrow::where('buyer_id', $user->id)
            ->where('status', 'locked')
            ->sum('amount');

        // Refundable (ready to be released/returned)
        $refundable = 0;

        // Seller released funds
        $refundable += PropertyEscrow::where('seller_id', $user->id)
            ->where('status', 'released_to_seller')
            ->sum('amount');

        // Buyer refundable deposits
        $refundable += PropertyEscrow::where('buyer_id', $user->id)
            ->where('status', 'ready_for_refund') // adjust status name if needed
            ->sum('amount');

        // Rent escrow refunds
        $refundable += EscrowBalance::where('user_id', $user->id)
            ->where('status', 'released')
            ->sum('total_amount');

        // Totals
        $availableNow = $walletBalance + $refundable;
        $totalInSystem = $walletBalance + $locked + $refundable;

        return response()->json([
            "success"       => true,
            "wallet"        => (float) $walletBalance,  
            "locked"        => (float) $locked,         
            "refundable"    => (float) $refundable,    
            "available_now" => (float) $availableNow,   
            "total_in_app"  => (float) $totalInSystem,  
        ]);
    }
}