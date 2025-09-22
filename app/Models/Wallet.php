<?php

// app/Models/Wallet.php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Wallet extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'balance',
    ];

    protected $casts = [
        'balance' => 'decimal:2',
    ];

    // Relationships
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function transactions()
    {
        return $this->hasMany(WalletTransaction::class);
    }

    // Helper methods
    public function hasBalance($amount = 0)
    {
        return $this->balance >= $amount;
    }

    public function canWithdraw($amount)
    {
        return $this->balance >= $amount && $amount > 0;
    }

    public function addBalance($amount, $type = 'deposit', $refId = null, $description = null)
    {
        $balanceBefore = $this->balance;
        $this->balance = bcadd($this->balance, $amount, 2);
        $this->save();

        // Create transaction record
        WalletTransaction::create([
            'wallet_id' => $this->id,
            'amount' => $amount,
            'type' => $type,
            'ref_id' => $refId,
            'description' => $description,
            'balance_before' => $balanceBefore,
            'balance_after' => $this->balance,
        ]);

        return $this;
    }

    public function deductBalance($amount, $type = 'payment', $refId = null, $description = null)
    {
        if (!$this->canWithdraw($amount)) {
            throw new \Exception('Insufficient wallet balance');
        }

        $balanceBefore = $this->balance;
        $this->balance = bcsub($this->balance, $amount, 2);
        $this->save();

        // Create transaction record
        WalletTransaction::create([
            'wallet_id' => $this->id,
            'amount' => -$amount, // Negative for deduction
            'type' => $type,
            'ref_id' => $refId,
            'description' => $description,
            'balance_before' => $balanceBefore,
            'balance_after' => $this->balance,
        ]);

        return $this;
    }
}