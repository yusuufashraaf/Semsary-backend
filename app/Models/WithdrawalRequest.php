<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class WithdrawalRequest extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'amount',
        'gateway',
        'account_details',
        'status',
        'transaction_ref',
        'requested_at',
        'completed_at',
        'gateway_response',
    ];

    protected $casts = [
        'account_details' => 'array',
        'gateway_response' => 'array',
        'requested_at' => 'datetime',
        'completed_at' => 'datetime',
        'amount' => 'decimal:2',
    ];

    protected $hidden = [
        'gateway_response',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function getStatusLabelAttribute()
    {
        return match($this->status) {
            'processing' => 'Processing',
            'completed' => 'Completed',
            'failed' => 'Failed',
            default => 'Unknown'
        };
    }

    public function getFormattedAmountAttribute()
    {
        return number_format($this->amount, 2);
    }
}