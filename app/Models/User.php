<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Passport\HasApiTokens;
use PHPOpenSourceSaver\JWTAuth\Contracts\JWTSubject;

class User extends Authenticatable implements JWTSubject
{
    use Notifiable,HasApiTokens, HasFactory;

    protected $fillable = [
         'first_name',
        'last_name',
        'email',
        'password',
        'phone_number',
        'role',
        'status',
        'email_otp',
        'email_otp_expires_at',
        'whatsapp_otp',
        'whatsapp_otp_expires_at',
        'email_verified_at',
        'phone_verified_at',
        'id_image_url'
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'phone_verified_at' => 'datetime',
    ];

    public function getJWTIdentifier()
    {
        return $this->getKey();
    }

    public function getJWTCustomClaims()
    {
        return [];
    }
    public function properties()
    {
        return $this->hasMany(Property::class);
    }

    public function reviews()
    {
        return $this->hasMany(Review::class);
    }

    public function notifications()
    {
        return $this->hasMany(Notification::class);
    }

    public function purchases()
    {
        return $this->hasMany(Purchase::class);
    }

    public function bookings()
    {
        return $this->hasMany(Booking::class);
    }

//////////////////////////////////////////
//////////////////////////////////////////
// Admin dashboard

// New relationships for admin dashboard
public function transactions()
{
    return $this->hasMany(Transaction::class);
}

public function auditLogs()
{
    return $this->hasMany(AuditLog::class);
}

// Admin scopes
public function scopeAdmins($query)
{
    return $query->where('role', 'admin');
}

public function scopeOwners($query)
{
    return $query->where('role', 'owner');
}

public function scopeAgents($query)
{
    return $query->where('role', 'agent');
}

public function scopeActive($query)
{
    return $query->where('status', 'active');
}

// Admin helper methods
public function isAdmin(): bool
{
    return $this->role === 'admin';
}

public function isOwner(): bool
{
    return $this->role === 'owner';
}

public function isAgent(): bool
{
    return $this->role === 'agent';
}

// Statistics methods for dashboard
public function getTotalTransactionsAttribute(): int
{
    return $this->transactions()->count();
}

public function getTotalSpentAttribute(): float
{
    return $this->transactions()->where('status', 'success')->sum('amount');
}
}
