<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Review extends Model
{
    use HasFactory;

    protected $fillable = [
        'property_id',
        'user_id',
        'comment',
        'rating',
    ];

    protected $casts = [
        'rating' => 'integer',
    ];

    /**
     * Review belongs to a user (reviewer).
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Review belongs to a property.
     */
    public function property()
    {
        return $this->belongsTo(Property::class);
    }

    /**
     * Scope: Get reviews for all properties owned by a specific owner.
     */
    public function scopeForOwner($query, $ownerId)
    {
        return $query->whereHas('property', function ($q) use ($ownerId) {
            $q->where('owner_id', $ownerId);
        });
    }
}