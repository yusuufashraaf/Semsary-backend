<?php

// app/Models/Checkout.php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class Checkout extends Model
{
    use HasFactory;

    protected $fillable = [
        'rent_request_id',
        'requester_id',
        'requested_at',
        'status',
        'type',
        'reason',
        'deposit_return_percent',
        'agent_notes',
        'agent_decision',
        'owner_confirmation',
        'owner_notes',
        'owner_confirmed_at',
        'admin_note',
        'refund_purchase_id',
        'payout_purchase_id',
        'transaction_ref',
        'final_refund_amount',
        'final_payout_amount',
        'processed_at',
    ];

    protected $casts = [
        'requested_at' => 'datetime',
        'owner_confirmed_at' => 'datetime',
        'processed_at' => 'datetime',
        'agent_decision' => 'array',
        'deposit_return_percent' => 'decimal:2',
        'final_refund_amount' => 'decimal:2',
        'final_payout_amount' => 'decimal:2',
    ];

    // Relationships
    public function rentRequest()
    {
        return $this->belongsTo(RentRequest::class);
    }

    public function requester()
    {
        return $this->belongsTo(User::class, 'requester_id');
    }

    public function refundPurchase()
    {
        return $this->belongsTo(Purchase::class, 'refund_purchase_id');
    }

    public function payoutPurchase()
    {
        return $this->belongsTo(Purchase::class, 'payout_purchase_id');
    }

    // Scopes
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeAwaitingOwnerConfirmation($query)
    {
        return $query->where('owner_confirmation', 'pending');
    }

    public function scopeExpired($query)
    {
        return $query->where('status', 'pending')
                    ->where('owner_confirmation', 'pending')
                    ->where('requested_at', '<', Carbon::now()->subHours(72));
    }

    // Helper methods
    public function isExpired()
    {
        return $this->status === 'pending' 
            && $this->owner_confirmation === 'pending'
            && $this->requested_at->lt(Carbon::now()->subHours(72));
    }

    public function canBeConfirmedByOwner()
    {
        return $this->status === 'pending' && $this->owner_confirmation === 'pending';
    }

    public function isFinalized()
    {
        return in_array($this->status, ['confirmed', 'auto_confirmed']);
    }
}