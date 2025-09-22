<?php

namespace App\Services;

use App\Models\Wallet;
use App\Models\EscrowBalance;

class BalanceService
{
    public static function getUserBalances(int $userId): array
    {
        $walletBalance = (float) Wallet::where('user_id', $userId)->value('balance') ?? 0.0;
        $escrowBalance = (float) EscrowBalance::where('user_id', $userId)
            ->locked()
            ->sum('total_amount');

        return [
            'wallet_balance' => $walletBalance,
            'escrow_balance' => $escrowBalance,
            'total_balance' => $walletBalance + $escrowBalance,
        ];
    }
}