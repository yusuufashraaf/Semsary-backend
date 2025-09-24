<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PropertyEscrow;
use App\Models\Wallet;
use Illuminate\Http\Request;

class BalanceApiController extends Controller
{
    public function show(Request $request)
    {
        $user = $request->user();

        // All escrows where user is buyer or seller
        $escrows = PropertyEscrow::where(function ($q) use ($user) {
                $q->where('buyer_id', $user->id)
                  ->orWhere('seller_id', $user->id);
            })
            ->get();

        // Locked funds (cannot use yet)
        $locked = $escrows->where('status', 'locked')->sum('amount');

        // Refundable funds (escrow ready to return/release)
        $refundable = $escrows->filter(fn ($escrow) => $escrow->isReadyForRelease())->sum('amount');

        // Total in escrow (locked + refundable)
        $totalEscrow = $escrows->sum('amount');

        // Wallet balance
        $walletBalance = Wallet::where('user_id', $user->id)->value('balance') ?? 0;

        // Available now = wallet + refundable
        $availableNow = $walletBalance + $refundable;

        // Total money in system = wallet + escrow
        $totalMoney = $walletBalance + $totalEscrow;

        return response()->json([
            "available_now" => (float) $availableNow,           
            "in_wallet" => (float) $walletBalance,              
            "in_escrow_locked" => (float) $locked,              
            "in_escrow_refundable" => (float) $refundable,      
            "total_money" => (float) $totalMoney,               
        ]);
    }
}