<?php

// app/Models/Purchase.php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Purchase extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'property_id',
        'rent_request_id',
        'amount',
        'deposit_amount',
        'payment_type',
        'status',
        'payment_gateway',
        'transaction_ref',
        'idempotency_key',
        'payment_details',
        'metadata',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'deposit_amount' => 'decimal:2',
        'payment_details' => 'array',
        'metadata' => 'array',
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

    public function rentRequest()
    {
        return $this->belongsTo(RentRequest::class);
    }

    public function refundCheckouts()
    {
        return $this->hasMany(Checkout::class, 'refund_purchase_id');
    }

    public function payoutCheckouts()
    {
        return $this->hasMany(Checkout::class, 'payout_purchase_id');
    }

    // Scopes
    public function scopeSuccessful($query)
    {
        return $query->where('status', 'successful');
    }

    public function scopeRefunds($query)
    {
        return $query->where('payment_type', 'refund');
    }

    public function scopePayouts($query)
    {
        return $query->where('payment_type', 'payout');
    }

    public function scopeForRentRequest($query, $rentRequestId)
    {
        return $query->where('rent_request_id', $rentRequestId);
    }

    public function scopeByType($query, $type)
    {
        return $query->where('payment_type', $type);
    }

    // Helper methods
    public function isSuccessful()
    {
        return $this->status === 'successful';
    }

    public function isRefund()
    {
        return $this->payment_type === 'refund';
    }

    public function isPayout()
    {
        return $this->payment_type === 'payout';
    }

    public function isRent()
    {
        return $this->payment_type === 'rent';
    }

    public function isDeposit()
    {
        return $this->payment_type === 'deposit';
    }
}