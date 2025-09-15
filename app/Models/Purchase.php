<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Purchase extends Model
{
    use HasFactory;

    protected $primaryKey = 'purchase_id';
    
    protected $fillable = [
        'user_id',
        'property_id',
        'status',
        'amount',
        'deposit_amount',
        'payment_gateway',
        'transaction_id',
        'payment_details'
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'deposit_amount' => 'decimal:2',
        'payment_details' => 'array'
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function property()
    {
        return $this->belongsTo(Property::class);
    }
}