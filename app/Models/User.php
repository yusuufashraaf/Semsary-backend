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
}
