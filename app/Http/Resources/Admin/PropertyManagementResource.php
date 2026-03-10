<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Property Management Resource for Admin Dashboard
 *
 * Formats property data for admin listing views with essential information
 */
class PropertyManagementResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'type' => $this->type,
            'status' => $this->property_state,
            'status_label' => $this->getStatusLabel(),
            'status_color' => $this->getStatusColor(),

            // Property details
            'price' => [
                'amount' => (float) $this->price,
                'type' => $this->price_type,
                'formatted' => $this->getFormattedPrice(),
            ],

            'specifications' => [
                'bedrooms' => $this->bedrooms,
                'bathrooms' => $this->bathrooms,
                'size' => $this->size,
                'size_unit' => 'sqft'
            ],

            // Location
            'location' => [
                'address' => $this->location['address'] ?? null,
                'city' => $this->location['city'] ?? null,
                'state' => $this->location['state'] ?? null,
                'coordinates' => [
                    'lat' => $this->location['latitude'] ?? $this->location['lat'] ?? null,
                    'lng' => $this->location['longitude'] ?? $this->location['lng'] ?? null,
                ]
            ],

            // Owner information
            'owner' => $this->when($this->relationLoaded('owner'), function () {
                return [
                    'id' => $this->owner->id,
                    'name' => trim($this->owner->first_name . ' ' . $this->owner->last_name),
                    'email' => $this->owner->email,
                    'phone' => $this->owner->phone ?? null,
                    'status' => $this->owner->status ?? 'active',
                    'joined_date' => $this->owner->created_at->format('M d, Y'),
                ];
            }),

            // CS Agent Assignment information
            'assignment' => [
                'is_assigned' => $this->isAssigned(),
                'status' => $this->getAssignmentStatus(),
                'agent' => $this->when($this->isAssigned(), function () {
                    $assignment = $this->getCurrentAssignment();
                    return $assignment ? [
                        'id' => $assignment->csAgent->id,
                        'name' => trim($assignment->csAgent->first_name . ' ' . $assignment->csAgent->last_name),
                        'email' => $assignment->csAgent->email,
                    ] : null;
                }),
                'assignment_id' => $this->when($this->isAssigned(), function () {
                    $assignment = $this->getCurrentAssignment();
                    return $assignment ? $assignment->id : null;
                }),
                'assigned_at' => $this->when($this->isAssigned(), function () {
                    $assignment = $this->getCurrentAssignment();
                    return $assignment ? $assignment->assigned_at->format('M d, Y H:i') : null;
                }),
            ],

            // Media
            'images' => [
                'count' => $this->whenLoaded('images', function () {
                    return $this->images->count();
                }, 0),
                'primary_image' => $this->whenLoaded('images', function () {
                    return $this->images->first()?->image_url;
                }),
                'has_images' => $this->whenLoaded('images', function () {
                    return $this->images->count() > 0;
                }, false),
            ],

            // Statistics
            'statistics' => [
                'bookings_count' => $this->bookings_count ?? 0,
                'reviews_count' => $this->reviews_count ?? 0,
                'average_rating' => $this->when($this->relationLoaded('reviews'), function () {
                    return round($this->reviews->avg('rating') ?? 0, 1);
                }),
                'total_revenue' => $this->when($this->relationLoaded('transactions'), function () {
                    return $this->transactions->where('status', 'success')->sum('amount');
                }),
            ],

            // Admin specific data
            'admin_info' => [
                'requires_attention' => $this->requiresAttention(),
                'days_since_created' => $this->created_at->diffInDays(now()),
                'last_updated' => $this->updated_at->diffForHumans(),
                'issues' => $this->getAdminIssues(),
            ],

            // Timestamps
            'created_at' => $this->created_at->toISOString(),
            'updated_at' => $this->updated_at->toISOString(),
            'created_at_human' => $this->created_at->format('M d, Y H:i'),
            'updated_at_human' => $this->updated_at->diffForHumans(),
        ];
    }

    /**
     * Get human-readable status label
     */
    private function getStatusLabel(): string
    {
        $labels = [
            'Valid' => 'Approved',
            'Invalid' => 'Rejected',
            'Pending' => 'Pending Review',
            'Rented' => 'Rented',
            'Sold' => 'Sold'
        ];

        return $labels[$this->property_state] ?? $this->property_state;
    }

    /**
     * Get status color for UI
     */
    private function getStatusColor(): string
    {
        $colors = [
            'Valid' => 'success',      // Green
            'Invalid' => 'danger',     // Red
            'Pending' => 'warning',    // Yellow/Orange
            'Rented' => 'info',        // Blue
            'Sold' => 'secondary'      // Gray
        ];

        return $colors[$this->property_state] ?? 'secondary';
    }

    /**
     * Get formatted price string
     */
    private function getFormattedPrice(): string
    {
        $amount = number_format($this->price, 0);
        $currency = '$'; // You can make this dynamic based on location/settings

        $typeLabels = [
            'FullPay' => '',
            'Monthly' => '/month',
            'Daily' => '/day'
        ];

        $suffix = $typeLabels[$this->price_type] ?? '';

        return $currency . $amount . $suffix;
    }

    /**
     * Check if property requires admin attention
     */
    private function requiresAttention(): bool
    {
        // Property has been pending for more than 7 days
        if ($this->property_state === 'Pending' && $this->created_at->lt(now()->subDays(7))) {
            return true;
        }

        // Property has no images
        if ($this->relationLoaded('images') && $this->images->isEmpty()) {
            return true;
        }

        // Property has no owner
        if (!$this->owner_id) {
            return true;
        }

        return false;
    }

    /**
     * Get list of admin issues
     */
    private function getAdminIssues(): array
    {
        $issues = [];

        if ($this->property_state === 'Pending' && $this->created_at->lt(now()->subDays(7))) {
            $issues[] = 'Long pending review (' . $this->created_at->diffInDays(now()) . ' days)';
        }

        if ($this->relationLoaded('images') && $this->images->isEmpty()) {
            $issues[] = 'No images uploaded';
        }

        if (!$this->owner_id) {
            $issues[] = 'No owner assigned';
        }

        if ($this->relationLoaded('owner') && $this->owner && $this->owner->status !== 'active') {
            $issues[] = 'Owner account not active';
        }

        if (empty(trim($this->description)) || strlen(trim($this->description)) < 10) {
            $issues[] = 'Insufficient description';
        }

        return $issues;
    }

    /**
     * Check if property is assigned to a CS agent
     */
    private function isAssigned(): bool
    {
        return $this->activeAssignment || $this->currentAssignment;
    }

    /**
     * Get assignment status
     */
    private function getAssignmentStatus(): ?string
    {
        if ($this->activeAssignment) {
            return $this->activeAssignment->status;
        }

        if ($this->currentAssignment) {
            return $this->currentAssignment->status;
        }

        return null;
    }

    /**
     * Get current assignment (active or latest)
     */
    private function getCurrentAssignment()
    {
        return $this->activeAssignment ?? $this->currentAssignment;
    }
}
