<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Http\Resources\Admin\UserDetailResource;

/**
 * Property Detail Resource for Admin Single Property View
 *
 * Comprehensive property data for detailed admin views with all relationships
 */
class PropertyDetailResource extends JsonResource
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
            'description' => $this->description,
            'type' => $this->type,
            'status' => $this->property_state,
            'status_label' => $this->getStatusLabel(),
            'status_color' => $this->getStatusColor(),

            // Detailed property information
            'property_details' => [
                'bedrooms' => $this->bedrooms,
                'bathrooms' => $this->bathrooms,
                'size' => $this->size,
                'size_unit' => 'sqft',
                'price' => (float) $this->price,
                'price_type' => $this->price_type,
                'formatted_price' => $this->getFormattedPrice(),
            ],

            // Complete location information
            'location' => [
                'address' => $this->location['address'] ?? null,
                'city' => $this->location['city'] ?? null,
                'state' => $this->location['state'] ?? null,
                'zip_code' => $this->location['zip_code'] ?? null,
                'country' => $this->location['country'] ?? null,
                'coordinates' => [
                    'latitude' => $this->location['latitude'] ?? $this->location['lat'] ?? null,
                    'longitude' => $this->location['longitude'] ?? $this->location['lng'] ?? null,
                ],
                'full_address' => $this->getFullAddress(),
            ],

            // Owner details
            'owner' => $this->when($this->relationLoaded('owner') && $this->owner, function () {
                return new UserDetailResource($this->owner);
            }),

            // Assigned CS Agent details
            'assigned_agent' => $this->when($this->relationLoaded('activeAssignment') && $this->activeAssignment, function () {
                return [
                    'id' => $this->activeAssignment->csAgent->id,
                    'name' => $this->activeAssignment->csAgent->first_name . ' ' . $this->activeAssignment->csAgent->last_name,
                    'email' => $this->activeAssignment->csAgent->email,
                    'assignment_id' => $this->activeAssignment->id,
                    'assignment_status' => $this->activeAssignment->status,
                    'assigned_at' => $this->activeAssignment->assigned_at->format('M d, Y H:i'),
                    'assigned_by' => $this->activeAssignment->assignedBy ?
                        $this->activeAssignment->assignedBy->first_name . ' ' . $this->activeAssignment->assignedBy->last_name : null,
                ];
            }, function () {
                // Check if there's a current assignment (not necessarily active)
                if ($this->relationLoaded('currentAssignment') && $this->currentAssignment) {
                    return [
                        'id' => $this->currentAssignment->csAgent->id,
                        'name' => $this->currentAssignment->csAgent->first_name . ' ' . $this->currentAssignment->csAgent->last_name,
                        'email' => $this->currentAssignment->csAgent->email,
                        'assignment_id' => $this->currentAssignment->id,
                        'assignment_status' => $this->currentAssignment->status,
                        'assigned_at' => $this->currentAssignment->assigned_at->format('M d, Y H:i'),
                        'assigned_by' => $this->currentAssignment->assignedBy ?
                            $this->currentAssignment->assignedBy->first_name . ' ' . $this->currentAssignment->assignedBy->last_name : null,
                    ];
                }
                return null;
            }),

            // Images and media
            'images' => $this->when($this->relationLoaded('images'), function () {
                return $this->images->map(function ($image) {
                    return [
                        'id' => $image->id,
                        'url' => $image->image_url,
                        'alt_text' => $image->alt_text ?? $this->title,
                        'is_primary' => $image->is_primary ?? false,
                        'uploaded_at' => $image->created_at->format('M d, Y H:i'),
                    ];
                });
            }),

            // Documents
            'documents' => $this->when($this->relationLoaded('documents'), function () {
                return $this->documents->map(function ($document) {
                    return [
                        'id' => $document->id,
                        'name' => $document->document_name,
                        'type' => $document->document_type,
                        'url' => $document->document_url,
                        'size' => $document->file_size ?? null,
                        'uploaded_at' => $document->created_at->format('M d, Y H:i'),
                    ];
                });
            }),

            // Features/Amenities
            'features' => $this->when($this->relationLoaded('features'), function () {
                return $this->features->map(function ($feature) {
                    return [
                        'id' => $feature->id,
                        'name' => $feature->name,
                        'category' => $feature->category ?? null,
                        'icon' => $feature->icon ?? null,
                    ];
                });
            }),

            // Recent bookings
            'bookings' => $this->when($this->relationLoaded('bookings'), function () {
                return $this->bookings->map(function ($booking) {
                    return [
                        'id' => $booking->id,
                        'status' => $booking->status,
                        'check_in' => $booking->check_in,
                        'check_out' => $booking->check_out,
                        'guest_count' => $booking->guest_count ?? 1,
                        'total_amount' => (float) ($booking->total_amount ?? 0),
                        'user' => [
                            'id' => $booking->customer->id ?? null,
                            'name' => trim(($booking->customer->first_name ?? '') . ' ' . ($booking->customer->last_name ?? '')),
                            'email' => $booking->customer->email ?? null,
                        ],
                        'created_at' => $booking->created_at->format('M d, Y H:i'),
                    ];
                });
            }),

            // Recent reviews
            'reviews' => $this->when($this->relationLoaded('reviews'), function () {
                return $this->reviews->map(function ($review) {
                    return [
                        'id' => $review->id,
                        'rating' => (int) $review->rating,
                        'comment' => $review->comment,
                        'user' => [
                            'id' => $review->user->id ?? null,
                            'name' => trim(($review->user->first_name ?? '') . ' ' . ($review->user->last_name ?? '')),
                        ],
                        'created_at' => $review->created_at->format('M d, Y H:i'),
                        'created_at_human' => $review->created_at->diffForHumans(),
                    ];
                });
            }),

            // Financial information
            'transactions' => $this->when($this->relationLoaded('transactions'), function () {
                return $this->transactions->map(function ($transaction) {
                    return [
                        'id' => $transaction->id,
                        'type' => $transaction->type,
                        'amount' => (float) $transaction->amount,
                        'status' => $transaction->status,
                        'description' => $transaction->description,
                        'created_at' => $transaction->created_at->format('M d, Y H:i'),
                    ];
                });
            }),

            // Statistics and metrics
            'statistics' => [
                'bookings_count' => $this->bookings_count ?? 0,
                'reviews_count' => $this->reviews_count ?? 0,
                'average_rating' => $this->when($this->relationLoaded('reviews'), function () {
                    return round($this->reviews->avg('rating') ?? 0, 1);
                }),
                'total_revenue' => $this->when($this->relationLoaded('transactions'), function () {
                    return (float) $this->transactions->where('status', 'success')->sum('amount');
                }),
                'views_count' => $this->views_count ?? 0,
                'inquiries_count' => $this->inquiries_count ?? 0,
            ],

            // SEO and visibility
            'seo' => [
                'slug' => $this->slug ?? null,
                'meta_title' => $this->meta_title ?? $this->title,
                'meta_description' => $this->meta_description ?? substr(strip_tags($this->description), 0, 160),
                'featured' => $this->featured ?? false,
                'priority' => $this->priority ?? 0,
            ],

            // Admin specific information
            'admin_info' => [
                'requires_attention' => $this->requiresAttention(),
                'issues' => $this->getAdminIssues(),
                'compliance_score' => $this->getComplianceScore(),
                'quality_score' => $this->getQualityScore(),
                'verification_status' => $this->getVerificationStatus(),
                'admin_notes' => $this->admin_notes ?? null,
                'flagged' => $this->flagged ?? false,
                'flag_reason' => $this->flag_reason ?? null,
            ],

            // Activity timeline
            'activity' => [
                'created_at' => $this->created_at->toISOString(),
                'updated_at' => $this->updated_at->toISOString(),
                'created_at_human' => $this->created_at->format('M d, Y H:i'),
                'updated_at_human' => $this->updated_at->diffForHumans(),
                'days_since_created' => $this->created_at->diffInDays(now()),
                'days_since_updated' => $this->updated_at->diffInDays(now()),
                'last_booking' => $this->when($this->relationLoaded('bookings') && $this->bookings->isNotEmpty(), function () {
                    return $this->bookings->first()->created_at->diffForHumans();
                }),
                'last_review' => $this->when($this->relationLoaded('reviews') && $this->reviews->isNotEmpty(), function () {
                    return $this->reviews->first()->created_at->diffForHumans();
                }),
            ],

            // System metadata
            'metadata' => [
                'indexed_for_search' => $this->indexed_for_search ?? true,
                'cache_expires_at' => $this->cache_expires_at ?? null,
                'last_sync' => $this->last_sync ?? null,
                'external_id' => $this->external_id ?? null,
                'import_source' => $this->import_source ?? null,
            ],
        ];
    }

    /**
     * Get human-readable status label
     */
    private function getStatusLabel(): string
    {
        $labels = [
            'Valid' => 'Approved & Active',
            'Invalid' => 'Rejected',
            'Pending' => 'Pending Admin Review',
            'Rented' => 'Currently Rented',
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
            'Valid' => 'success',
            'Invalid' => 'danger',
            'Pending' => 'warning',
            'Rented' => 'info',
            'Sold' => 'secondary'
        ];

        return $colors[$this->property_state] ?? 'secondary';
    }

    /**
     * Get formatted price string - FIXED
     */
    private function getFormattedPrice(): string
    {
        $amount = number_format($this->price, 0);
        $currency = '$'; // Fixed the missing quote

        $typeLabels = [
            'FullPay' => '',
            'Monthly' => '/month',
            'Daily' => '/day'
        ];

        $suffix = $typeLabels[$this->price_type] ?? '';

        return $currency . $amount . $suffix;
    }

    /**
     * Get full formatted address
     */
    private function getFullAddress(): string
    {
        $parts = array_filter([
            $this->location['address'] ?? null,
            $this->location['city'] ?? null,
            $this->location['state'] ?? null,
            $this->location['zip_code'] ?? null,
            $this->location['country'] ?? null,
        ]);

        return implode(', ', $parts);
    }

    /**
     * Check if property requires admin attention
     */
    private function requiresAttention(): bool
    {
        return count($this->getAdminIssues()) > 0;
    }

    /**
     * Get comprehensive list of admin issues
     */
    private function getAdminIssues(): array
    {
        $issues = [];

        // Status-related issues
        if ($this->property_state === 'Pending' && $this->created_at->lt(now()->subDays(7))) {
            $issues[] = [
                'type' => 'status',
                'severity' => 'high',
                'message' => 'Long pending review (' . $this->created_at->diffInDays(now()) . ' days)',
                'action_required' => 'Review and approve/reject',
            ];
        }

        // Media issues
        if ($this->relationLoaded('images') && $this->images->isEmpty()) {
            $issues[] = [
                'type' => 'media',
                'severity' => 'high',
                'message' => 'No images uploaded',
                'action_required' => 'Request images from owner',
            ];
        }

        // Owner issues
        if (!$this->owner_id) {
            $issues[] = [
                'type' => 'owner',
                'severity' => 'critical',
                'message' => 'No owner assigned',
                'action_required' => 'Assign property owner',
            ];
        }

        if ($this->relationLoaded('owner') && $this->owner && $this->owner->status !== 'active') {
            $issues[] = [
                'type' => 'owner',
                'severity' => 'medium',
                'message' => 'Owner account not active (' . $this->owner->status . ')',
                'action_required' => 'Contact owner or activate account',
            ];
        }

        // Content issues
        if (empty(trim($this->description)) || strlen(trim($this->description)) < 20) {
            $issues[] = [
                'type' => 'content',
                'severity' => 'medium',
                'message' => 'Insufficient description',
                'action_required' => 'Request detailed description',
            ];
        }

        // Pricing issues
        if ($this->price <= 0) {
            $issues[] = [
                'type' => 'pricing',
                'severity' => 'high',
                'message' => 'Invalid or missing price',
                'action_required' => 'Set valid price',
            ];
        }

        // Location issues
        if (empty($this->location['address'] ?? null)) {
            $issues[] = [
                'type' => 'location',
                'severity' => 'medium',
                'message' => 'Missing address information',
                'action_required' => 'Add complete address',
            ];
        }

        return $issues;
    }

    /**
     * Calculate compliance score (0-100)
     */
    private function getComplianceScore(): int
    {
        $score = 100;
        $issues = $this->getAdminIssues();

        foreach ($issues as $issue) {
            switch ($issue['severity']) {
                case 'critical':
                    $score -= 25;
                    break;
                case 'high':
                    $score -= 15;
                    break;
                case 'medium':
                    $score -= 10;
                    break;
                case 'low':
                    $score -= 5;
                    break;
            }
        }

        return max(0, $score);
    }

    /**
     * Calculate quality score based on completeness and engagement
     */
    private function getQualityScore(): int
    {
        $score = 0;

        // Basic information (40 points)
        if (!empty($this->title)) $score += 5;
        if (!empty($this->description) && strlen($this->description) >= 50) $score += 10;
        if ($this->price > 0) $score += 5;
        if (!empty($this->location['address'] ?? null)) $score += 10;
        if ($this->bedrooms > 0 && $this->bathrooms > 0) $score += 5;
        if ($this->size > 0) $score += 5;

        // Media (30 points)
        if ($this->relationLoaded('images')) {
            $imageCount = $this->images->count();
            if ($imageCount >= 1) $score += 10;
            if ($imageCount >= 5) $score += 10;
            if ($imageCount >= 10) $score += 10;
        }

        // Features (10 points)
        if ($this->relationLoaded('features') && $this->features->count() >= 3) {
            $score += 10;
        }

        // Engagement (20 points)
        if ($this->relationLoaded('reviews') && $this->reviews->count() > 0) {
            $score += 10;
        }
        if ($this->relationLoaded('bookings') && $this->bookings->count() > 0) {
            $score += 10;
        }

        return min(100, $score);
    }

    /**
     * Get verification status
     */
    private function getVerificationStatus(): array
    {
        return [
            'is_verified' => $this->is_verified ?? false,
            'verified_at' => $this->verified_at ?? null,
            'verified_by' => $this->verified_by ?? null,
            'verification_notes' => $this->verification_notes ?? null,
            'documents_verified' => $this->documents_verified ?? false,
            'identity_verified' => $this->relationLoaded('owner') &&
                                 $this->owner &&
                                 ($this->owner->identity_verified ?? false),
        ];
    }
}
