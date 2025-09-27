<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class UserBalanceController extends Controller
{
    public function getBalances(Request $request)
    {
        $user = Auth::user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'You must be logged in to view balances.',
            ], 401);
        }

        // Get wallet balance (available money)
        $walletBalance = DB::table('wallets')
            ->where('user_id', $user->id)
            ->value('balance') ?? 0;

        // Calculate returnable money from all escrow sources
        $returnableMoney = 0;

        // Property escrows - money as buyer that can be refunded
        $propertyEscrowsBuyer = DB::table('property_escrows')
            ->where('buyer_id', $user->id)
            ->where('status', 'locked')
            ->sum('amount');

        // Property escrows - money as seller that was released
        $propertyEscrowsSeller = DB::table('property_escrows')
            ->where('seller_id', $user->id)
            ->where('status', 'released_to_seller')
            ->sum('amount');

        // Rent escrows - money as renter that can be refunded
        $rentEscrowsRenter = DB::table('escrow_balances')
            ->where('user_id', $user->id)
            ->where('status', 'locked')
            ->sum('total_amount');

        // Rent escrows - money as owner that was released
        $rentEscrowsOwner = DB::table('escrow_balances')
            ->where('owner_id', $user->id)
            ->where('status', 'released_to_owner')
            ->sum('total_amount');

        // Calculate total returnable (can increase with releases to user, decrease when claimed)
        $returnableMoney = $propertyEscrowsBuyer + $propertyEscrowsSeller + $rentEscrowsRenter + $rentEscrowsOwner;

        return response()->json([
            'success' => true,
            'available_amount' => number_format($walletBalance, 2),
            'returnable_amount' => number_format($returnableMoney, 2),
            'total_accessible' => number_format($walletBalance + $returnableMoney, 2)
        ]);
    }
}