<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Passport\HasApiTokens;
use PHPOpenSourceSaver\JWTAuth\Contracts\JWTSubject;
use Illuminate\Database\Eloquent\Relations\HasMany;

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
        return $this->hasMany(Property::class, 'owner_id');
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

public function scopePending($query)
{
    return $query->where('status', 'pending');
}

public function scopeSuspended($query)
{
    return $query->where('status', 'suspended');
}

// Role check methods
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

public function isActive(): bool
{
    return $this->status === 'active';
}

public function isPending(): bool
{
    return $this->status === 'pending';
}

public function isSuspended(): bool
{
    return $this->status === 'suspended';
}

// NEW: Simple status change methods with admin action logging
public function activateUser(int $adminId, ?string $reason = null): bool
{
    if ($this->status !== 'active') {
        $this->update(['status' => 'active']);
        AdminAction::log($adminId, $this->id, 'activate', $reason);
        return true;
    }
    return false;
}

public function suspendUser(int $adminId, ?string $reason = null): bool
{
    if ($this->status !== 'suspended') {
        $this->update(['status' => 'suspended']);
        AdminAction::log($adminId, $this->id, 'suspend', $reason);
        return true;
    }
    return false;
}

public function blockUser(int $adminId, ?string $reason = null): bool
{
    if ($this->status !== 'pending') {
        $this->update(['status' => 'pending']);
        AdminAction::log($adminId, $this->id, 'pending', $reason);
        return true;
    }
    return false;
}

// Scope for filtering users with search and filters
public function scopeWithFilters($query, array $filters)
{
    return $query
        ->when($filters['role'] ?? null, function ($q, $role) {
            if (is_array($role)) {
                return $q->whereIn('role', $role);
            }
            return $q->where('role', $role);
        })
        ->when($filters['status'] ?? null, function ($q, $status) {
            if (is_array($status)) {
                return $q->whereIn('status', $status);
            }
            return $q->where('status', $status);
        })
        ->when($filters['search'] ?? null, function ($q, $search) {
            return $q->where(function ($query) use ($search) {
                $query->where('first_name', 'like', "%{$search}%")
                      ->orWhere('last_name', 'like', "%{$search}%")
                      ->orWhere('email', 'like', "%{$search}%");
            });
        })
        ->when($filters['date_from'] ?? null, function ($q, $dateFrom) {
            return $q->where('created_at', '>=', $dateFrom);
        })
        ->when($filters['date_to'] ?? null, function ($q, $dateTo) {
            return $q->where('created_at', '<=', $dateTo);
        });
}

// Helper method to get full name
public function getFullNameAttribute(): string
{
    return trim($this->first_name . ' ' . $this->last_name);
}

// Helper method for formatted location (if needed)
public function getLocationStringAttribute(): ?string
{
    if (is_array($this->location) && isset($this->location['address'])) {
        return $this->location['address'];
    }
    return null;
}

// Helper method for formatted price (if needed for user-related pricing)
public function getFormattedPriceAttribute(): ?string
{
    // This might be used for user-related pricing in the future
    return null;
}

//////////////////////////////////////////
//////////////////////////////////////////
// CS Agent Property verification

// NEW: CS Agent Property Assignment relationships
public function csAgentAssignments()
{
    return $this->hasMany(CSAgentPropertyAssign::class, 'cs_agent_id');
}

public function assignedProperties()
{
    return $this->hasMany(CSAgentPropertyAssign::class, 'assigned_by');
}

// NEW: CS Agent specific scopes
public function scopeCsAgents($query)
{
    return $query->where('role', 'agent')->where('status', 'active');
}

// NEW: CS Agent specific methods
public function isCsAgent(): bool
{
    return $this->role === 'agent'; // Using 'agent' role for CS agents
}

public function isActiveCsAgent(): bool
{
    return $this->isCsAgent() && $this->isActive();
}

// NEW: CS Agent assignment statistics methods
public function getActiveAssignmentsCount(): int
{
    return $this->csAgentAssignments()
        ->whereIn('status', ['pending', 'in_progress'])
        ->count();
}

public function getCompletedAssignmentsCount(): int
{
    return $this->csAgentAssignments()
        ->where('status', 'completed')
        ->count();
}

public function getCurrentAssignments()
{
    return $this->csAgentAssignments()
        ->whereIn('status', ['pending', 'in_progress'])
        ->with(['property', 'property.owner'])
        ->orderBy('assigned_at', 'desc');
}

public function getAverageCompletionTime(): ?float
{
    $completedAssignments = $this->csAgentAssignments()
        ->where('status', 'completed')
        ->whereNotNull('started_at')
        ->whereNotNull('completed_at')
        ->get();

    if ($completedAssignments->isEmpty()) {
        return null;
    }

    $totalHours = $completedAssignments->sum(function ($assignment) {
        return $assignment->started_at->diffInHours($assignment->completed_at);
    });

    return round($totalHours / $completedAssignments->count(), 2);
}
}
