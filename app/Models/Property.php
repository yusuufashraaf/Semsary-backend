<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Property extends Model
{
    protected $fillable = [
        'owner_id',
        'title',
        'description',
        'bedrooms',
        'bathrooms',
        'type',
        'price',
        'price_type',
        'location',
        'size',
        'property_state',
    ];

    protected $casts = [
        'location' => 'array', // Cast JSON to array
    ];

    public function features()
    {
        return $this->belongsToMany(Feature::class, 'property_features');
    }
    public function images()
    {
        return $this->hasMany(PropertyImage::class);
    }

    public function documents()
    {
        return $this->hasMany(PropertyDocument::class);
    }

    public function bookings(){
        return $this->hasMany(Booking::class);
    }

    public function reviews(){
        return $this->hasMany(Review::class);
    }
    public function user()//owner
    {
       return $this->belongsTo(User::class);
    }

//////////////////////////////////////////
//////////////////////////////////////////
// Admin dashboard

// New relationship for admin dashboard
public function transactions()
{
    return $this->hasMany(Transaction::class);
}

// Admin scopes
public function scopePending($query)
{
    return $query->where('property_state', 'Pending');
}

public function scopeValid($query)
{
    return $query->where('property_state', 'Valid');
}

public function scopeRented($query)
{
    return $query->where('property_state', 'Rented');
}

public function scopeSold($query)
{
    return $query->where('property_state', 'Sold');
}

// Admin helper methods
public function isPending(): bool
{
    return $this->property_state === 'Pending';
}

public function isValid(): bool
{
    return $this->property_state === 'Valid';
}

// Statistics methods for dashboard
public function getTotalRevenueAttribute(): float
{
    return $this->transactions()->where('status', 'success')->sum('amount');
}
}
