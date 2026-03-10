<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class PropertyPurchase extends Model
{
    protected $fillable = [
        'property_id',
        'buyer_id',
        'seller_id',
        'amount',
        'status',
        'payment_gateway',
        'transaction_ref',
        'idempotency_key',
        'purchase_date',
        'cancellation_deadline',
        'completion_date',
        'cancelled_at',
        'metadata',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'purchase_date' => 'datetime',
        'cancellation_deadline' => 'datetime',
        'completion_date' => 'datetime',
        'cancelled_at' => 'datetime',
        'metadata' => 'array',
    ];

    // Relationships
    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    public function buyer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'buyer_id');
    }

    public function seller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'seller_id');
    }

    public function escrow(): HasOne
    {
        return $this->hasOne(PropertyEscrow::class);
    }
}