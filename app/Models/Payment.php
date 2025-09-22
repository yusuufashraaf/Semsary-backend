<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    protected $fillable = [
        'user_id', 'transaction_id', 'amount', 'currency', 'status', 'raw_response'
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
