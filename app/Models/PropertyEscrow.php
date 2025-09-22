<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PropertyEscrow extends Model
{
    protected $fillable = [
        'property_purchase_id',
        'property_id',
        'buyer_id',
        'seller_id', 
        'amount',
        'status',
        'locked_at',
        'scheduled_release_at',
        'released_at',
        'release_reason',
        'payment_breakdown',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'locked_at' => 'datetime',
        'scheduled_release_at' => 'datetime',
        'released_at' => 'datetime',
        'payment_breakdown' => 'array',
    ];

    // ================= RELATIONSHIPS ================= //

    public function propertyPurchase(): BelongsTo
    {
        return $this->belongsTo(PropertyPurchase::class);
    }

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

    // ================= BUSINESS LOGIC ================= //

    // Check if escrow is ready for auto-release
    public function isReadyForRelease(): bool
    {
        return $this->status === 'locked'
            && $this->scheduled_release_at?->isPast();
    }

    // ================= SCOPES ================= //

    // Scope for escrows ready to be released
    public function scopeReadyForRelease($query)
    {
        return $query->where('status', 'locked')
                     ->where('scheduled_release_at', '<=', now());
    }
}