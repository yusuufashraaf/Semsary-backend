<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RentRequest extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'property_id',
        'check_in',
        'check_out',
        'status',
        'blocked_until',
        'payment_deadline',
        'cooldown_expires_at',
    ];

    protected $casts = [
        'check_in' => 'date',
        'check_out' => 'date',
        'blocked_until' => 'datetime',
        'payment_deadline' => 'datetime',
        'cooldown_expires_at' => 'datetime',
    ];

    // Relationships
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function property()
    {
        return $this->belongsTo(Property::class);
    }

   public function checkout()
{
    return $this->hasOne(Checkout::class);
}

public function escrowBalance()
{
    return $this->hasOne(EscrowBalance::class);
}

public function purchases()
{
    return $this->hasMany(Purchase::class);
}

public function rentPurchases()
{
    return $this->hasMany(Purchase::class)->where('payment_type', 'rent');
}

public function depositPurchases()
{
    return $this->hasMany(Purchase::class)->where('payment_type', 'deposit');
}

public function refundPurchases()
{
    return $this->hasMany(Purchase::class)->where('payment_type', 'refund');
}

public function payoutPurchases()
{
    return $this->hasMany(Purchase::class)->where('payment_type', 'payout');
}

// Helper methods for RentRequest model
public function hasEscrow()
{
    return $this->escrowBalance && $this->escrowBalance->isLocked();
}

public function canCheckout()
{
    return $this->status === 'paid' && !$this->checkout;
}

public function getTotalPaid()
{
    return $this->purchases()->successful()->sum('amount');
}

public function hasCheckout()
{
    return $this->checkout !== null;
}
public function escrow()
{
    return $this->hasOne(PropertyEscrow::class, 'property_purchase_id');
}

}