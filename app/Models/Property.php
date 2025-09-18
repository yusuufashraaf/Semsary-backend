<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Property extends Model
{
    use HasFactory;

    protected $table = 'properties';
    protected $appends = ['is_in_wishlist'];

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
        'status',
    ];

    protected $casts = [
        'location' => 'array',   // JSON → array
        'bedrooms' => 'integer',
        'bathrooms' => 'integer',
        'price' => 'decimal:2',
    ];

    /* ---------------- Relationships ---------------- */

    // Owner of the property
    public function owner()
    {
        return $this->belongsTo(User::class, 'owner_id');
    }




    // Features / amenities
    public function features()
    {
        return $this->belongsToMany(Feature::class, 'property_features', 'property_id', 'feature_id');
    }

    // Property images
    public function images()
    {
        return $this->hasMany(PropertyImage::class, 'property_id');
    }

    // Optional: documents related to property
    public function documents()
    {
        return $this->hasMany(PropertyDocument::class, 'property_id');
    }

    // Bookings
    public function bookings()
    {
        return $this->hasMany(Booking::class, 'property_id');
    }

    // Reviews
    public function reviews()
    {
        return $this->hasMany(Review::class, 'property_id');
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

public function scopeInvalid($query)
{
    return $query->where('property_state', 'Invalid');
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

// Helper method for formatted price
public function getFormattedPriceAttribute(): string
{
    return number_format($this->price, 2) . ' EGP';
}

// Helper method for location string
public function getLocationStringAttribute(): ?string
{
    if (is_array($this->location) && isset($this->location['address'])) {
        return $this->location['address'];
    }
    return null;
}

// Helper method to get primary image
public function getPrimaryImageAttribute(): ?string
{
    $primaryImage = $this->images()->where('is_primary', true)->first();
    if ($primaryImage) {
        return $primaryImage->image_url;
    }

    $firstImage = $this->images()->first();
    return $firstImage ? $firstImage->image_url : null;
}

// Scope for filtering properties with search and filters
public function scopeWithFilters($query, array $filters)
{
    return $query
        ->when($filters['property_state'] ?? null, function ($q, $state) {
            if (is_array($state)) {
                return $q->whereIn('property_state', $state);
            }
            return $q->where('property_state', $state);
        })
        ->when($filters['type'] ?? null, function ($q, $type) {
            if (is_array($type)) {
                return $q->whereIn('type', $type);
            }
            return $q->where('type', $type);
        })
        ->when($filters['price_min'] ?? null, function ($q, $priceMin) {
            return $q->where('price', '>=', $priceMin);
        })
        ->when($filters['price_max'] ?? null, function ($q, $priceMax) {
            return $q->where('price', '<=', $priceMax);
        })
        ->when($filters['search'] ?? null, function ($q, $search) {
            return $q->where(function ($query) use ($search) {
                $query->where('title', 'like', "%{$search}%")
                      ->orWhere('description', 'like', "%{$search}%");
            });
        })
        ->when($filters['owner_id'] ?? null, function ($q, $ownerId) {
            return $q->where('owner_id', $ownerId);
        })
        ->when($filters['date_from'] ?? null, function ($q, $dateFrom) {
            return $q->where('created_at', '>=', $dateFrom);
        })
        ->when($filters['date_to'] ?? null, function ($q, $dateTo) {
            return $q->where('created_at', '<=', $dateTo);
        });
}

//////////////////////////////////////////
//////////////////////////////////////////
// CS Agent dashboard

// NEW: CS Agent Property Assignment relationships
public function csAgentAssignments(): HasMany
{
    return $this->hasMany(CSAgentPropertyAssign::class);
}

// Get the current/latest assignment
public function currentAssignment(): HasOne
{
    return $this->hasOne(CSAgentPropertyAssign::class)->latest('assigned_at');
}

// Get the active assignment (in progress or pending)
public function activeAssignment(): HasOne
{
    return $this->hasOne(CSAgentPropertyAssign::class)
                ->whereIn('status', ['pending', 'in_progress'])
                ->latest('assigned_at');
}

public function latestAssignment()
{
    return $this->hasOne(CSAgentPropertyAssign::class, 'property_id')
        ->latest('assigned_at');
}

// Get completed assignments
public function completedAssignments(): HasMany
{
    return $this->hasMany(CSAgentPropertyAssign::class)
                ->where('status', 'completed')
                ->orderBy('completed_at', 'desc');
}

// NEW: CS Agent related scopes
public function scopeUnassigned($query)
{
    return $query->doesntHave('activeAssignment');
}

public function scopeAssignedToAgent($query, int $agentId)
{
    return $query->whereHas('activeAssignment', function ($q) use ($agentId) {
        $q->where('cs_agent_id', $agentId);
    });
}

// Method to check if property requires attention (for admin dashboard)
public function requiresAttention(): bool
{
    // Property requires attention if it's been pending for more than 7 days
    // or if it has an overdue assignment
    if ($this->property_state === 'Pending' && $this->created_at->diffInDays(now()) > 7) {
        return true;
    }

    $activeAssignment = $this->getActiveAssignment();
    if ($activeAssignment && $activeAssignment->assigned_at->diffInDays(now()) > 7) {
        return true;
    }

    return false;
}

// NEW: Property state management methods
public function markAsValid(int $adminId, ?string $reason = null): bool
{
    if ($this->property_state !== 'Valid') {
        $this->update(['property_state' => 'Valid']);
        // Log admin action if AdminAction model exists
        if (class_exists('App\Models\AdminAction')) {
            AdminAction::log($adminId, $this->id, 'approve_property', $reason);
        }
        return true;
    }
    return false;
}

public function markAsInvalid(int $adminId, ?string $reason = null): bool
{
    if ($this->property_state !== 'Invalid') {
        $this->update(['property_state' => 'Invalid']);
        // Log admin action if AdminAction model exists
        if (class_exists('App\Models\AdminAction')) {
            AdminAction::log($adminId, $this->id, 'reject_property', $reason);
        }
        return true;
    }
    return false;
}

// NEW: CS Agent helper methods
    public function hasActiveAssignment(): bool
    {
        return $this->activeAssignment()->exists();
    }

    public function getCurrentCSAgent(): ?User
    {
        $assignment = $this->activeAssignment;
        return $assignment ? $assignment->csAgent : null;
    }

    public function getActiveAssignment(): ?CSAgentPropertyAssign
{
    return $this->csAgentAssignments()
        ->whereIn('status', ['pending', 'in_progress'])
        ->latest('assigned_at')
        ->first();
}

    public function getAssignmentStatus(): ?string
    {
        $assignment = $this->activeAssignment;
        return $assignment ? $assignment->status : null;
    }

    public function isAssignedTo(int $agentId): bool
    {
        return $this->activeAssignment()
                    ->where('cs_agent_id', $agentId)
                    ->exists();
    }

    public function canBeAssigned(): bool
    {
        // Can be assigned if no active assignment or if current assignment is completed/rejected
        return !$this->hasActiveAssignment();
    }

    public function getLastCompletedAssignment(): ?CSAgentPropertyAssign
    {
        return $this->completedAssignments()->first();
    }

    public function getTotalAssignmentsCount(): int
    {
        return $this->csAgentAssignments()->count();
    }

    public function getCompletedAssignmentsCount(): int
    {
        return $this->csAgentAssignments()->where('status', 'completed')->count();
    }

    public function getAverageAssignmentDuration(): ?float
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
    public function getIsInWishlistAttribute()
    {
        if (!Auth::check()) {
            return false;
        }

        return $this->wishlists()
            ->where('user_id', Auth::id())
            ->exists();
    }

    public function wishlists()
    {
        return $this->hasMany(Wishlist::class);
    }

}