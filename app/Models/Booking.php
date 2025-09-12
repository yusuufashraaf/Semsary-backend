<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Booking extends Model
{
    protected $fillable = [
        'property_id',
        'user_id',
        'start_date',
        'end_date',
        'total_price',
        'status',
    ];

    public function customer()
    {
        return $this->belongsTo(User::class);
    }

    public function property()
    {
        return $this->belongsTo(Property::class);
    }

    public function scopeForOwner($query, $ownerId)
    {
        return $query->whereHas('property', function ($q)use ($ownerId) {
            $q->where('owner_id', $ownerId);
        });

    }
}
