<?php

// app/Models/EscrowBalance.php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EscrowBalance extends Model
{
    use HasFactory;

    protected $fillable = [
        'rent_request_id',
        'user_id',
        'rent_amount',
        'deposit_amount',
        'total_amount',
        'status',
        'locked_at',
        'released_at',
        'rent_released',
        'rent_released_at',
    ];

    protected $casts = [
        'rent_amount' => 'decimal:2',
        'deposit_amount' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'locked_at' => 'datetime',
        'released_at' => 'datetime',
        'rent_released' => 'boolean',
        'rent_released_at' => 'datetime',
    ];

    // Relationships
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function rentRequest()
    {
        return $this->belongsTo(RentRequest::class);
    }

    // Scopes
    public function scopeLocked($query)
    {
        return $query->where('status', 'locked');
    }

    public function scopeReleased($query)
    {
        return $query->where('status', 'released');
    }

    // Helper methods
    public function isLocked()
    {
        return $this->status === 'locked';
    }

    public function isReleased()
    {
        return $this->status === 'released';
    }

    public function getTotalAmount()
    {
        return bcadd($this->rent_amount, $this->deposit_amount, 2);
    }
}