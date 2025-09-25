<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Chat extends Model
{
    use HasFactory;

    protected $fillable = [
        'id',
        'property_id',
        'owner_id',
        'renter_id',
        'last_message_at',
        'assigned_agent_id',
    ];

    protected $casts = [
        'last_message_at' => 'datetime',
    ];

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function renter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'renter_id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class)->latest();
    }

    public function latestMessage()
    {
        return $this->hasOne(Message::class)->latestOfMany();
    }

    public function participants()
    {
        return collect([$this->owner, $this->renter]);
    }

    public function getOtherUserAttribute()
    {
        return auth()->id() === $this->owner_id ? $this->renter : $this->owner;
    }

    public function assignedAgent(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_agent_id');
    }
}