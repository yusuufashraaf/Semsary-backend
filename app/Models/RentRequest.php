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

    public function payments()
    {
        return $this->hasMany(Purchase::class);
    }

    public function checkout()
    {
        return $this->hasOne(Checkout::class);
    }
}