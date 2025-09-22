<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Services\BalanceService;

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

        $balances = BalanceService::getUserBalances($user->id);

        return response()->json([
            'success' => true,
            'balances' => $balances,
        ]);
    }
}