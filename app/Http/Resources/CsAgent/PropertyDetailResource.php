<?php

namespace App\Http\Resources\CsAgent;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Property Detail Resource for CS Agent
 *
 * Provides comprehensive property data for CS agents with relevant information
 * for verification and processing tasks
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

            // Property details for verification
            'property_details' => [
                'bedrooms' => $this->bedrooms,
                'bathrooms' => $this->bathrooms,
                'size' => $this->size,
                'size_unit' => 'sqft',
                'price' => (float) $this->price,
                'price_type' => $this->price_type,
                'formatted_price' => $this->getFormattedPrice(),
            ],

            // Location information
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

            // Owner information (essential for contact)
            'owner' => $this->when($this->relationLoaded('owner') && $this->owner, function () {
                return [
                    'id' => $this->owner->id,
                    'name' => trim($this->owner->first_name . ' ' . $this->owner->last_name),
                    'first_name' => $this->owner->first_name,
                    'last_name' => $this->owner->last_name,
                    'email' => $this->owner->email,
                    'phone_number' => $this->owner->phone_number,
                    'status' => $this->owner->status,
                    'member_since' => $this->owner->created_at ? $this->owner->created_at->format('M Y') : null,
                ];
            }),

            // Property images for verification
            'images' => $this->when($this->relationLoaded('images'), function () {
                return $this->images->map(function ($image) {
                    return [
                        'id' => $image->id,
                        'url' => $image->image_url,
                        'alt_text' => $image->alt_text ?? $this->title,
                        'is_primary' => $image->is_primary ?? false,
                        'order_index' => $image->order_index ?? 0,
                        'uploaded_at' => $image->created_at ? $image->created_at->format('M d, Y H:i') : null,
                    ];
                })->sortBy('order_index')->values();
            }),

            // Property documents for verification
            'documents' => $this->when($this->relationLoaded('documents'), function () {
                return $this->documents->map(function ($document) {
                    return [
                        'id' => $document->id,
                        'name' => $document->document_name,
                        'type' => $document->document_type,
                        'url' => $document->document_url,
                        'size' => $document->file_size ?? null,
                        'uploaded_at' => $document->created_at ? $document->created_at->format('M d, Y H:i') : null,
                        'verified' => $document->verified ?? false,
                        'verification_notes' => $document->verification_notes ?? null,
                    ];
                });
            }),

            // Property features/amenities
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

            // Assignment information
            'assignment' => $this->when($this->relationLoaded('currentAssignment') && $this->currentAssignment, function () {
                return [
                    'id' => $this->currentAssignment->id,
                    'status' => $this->currentAssignment->status,
                    'formatted_status' => $this->currentAssignment->formatted_status,
                    'notes' => $this->currentAssignment->notes,
                    'priority' => $this->currentAssignment->priority,
                    'assigned_at' => $this->currentAssignment->assigned_at ? $this->currentAssignment->assigned_at->format('M d, Y H:i') : null,
                    'started_at' => $this->currentAssignment->started_at ? $this->currentAssignment->started_at->format('M d, Y H:i') : null,
                    'completed_at' => $this->currentAssignment->completed_at ? $this->currentAssignment->completed_at->format('M d, Y H:i') : null,
                    'duration_hours' => $this->currentAssignment->duration,
                    'assigned_by' => $this->currentAssignment->assignedBy ? [
                        'id' => $this->currentAssignment->assignedBy->id,
                        'name' => trim($this->currentAssignment->assignedBy->first_name . ' ' . $this->currentAssignment->assignedBy->last_name),
                    ] : null,
                ];
            }),

            // Recent reviews (limited for context)
            'recent_reviews' => $this->when($this->relationLoaded('reviews'), function () {
                return $this->reviews->take(5)->map(function ($review) {
                    return [
                        'id' => $review->id,
                        'rating' => (int) $review->rating,
                        'comment' => $review->comment,
                        'user_name' => $review->user ? trim($review->user->first_name . ' ' . $review->user->last_name) : 'Anonymous',
                        'created_at' => $review->created_at ? $review->created_at->format('M d, Y') : null,
                        'created_at_human' => $review->created_at ? $review->created_at->diffForHumans() : null,
                    ];
                });
            }),

            // Basic statistics
            'statistics' => [
                'reviews_count' => $this->reviews_count ?? 0,
                'bookings_count' => $this->bookings_count ?? 0,
                'average_rating' => $this->when($this->relationLoaded('reviews'), function () {
                    return round($this->reviews->avg('rating') ?? 0, 1);
                }),
                'images_count' => $this->when($this->relationLoaded('images'), function () {
                    return $this->images->count();
                }),
                'documents_count' => $this->when($this->relationLoaded('documents'), function () {
                    return $this->documents->count();
                }),
            ],

            // Verification checklist items
            'verification_checklist' => $this->getVerificationChecklist(),

            // Activity timeline
            'activity' => [
                'created_at' => $this->created_at ? $this->created_at->toISOString() : null,
                'updated_at' => $this->updated_at ? $this->updated_at->toISOString() : null,
                'created_at_human' => $this->created_at ? $this->created_at->format('M d, Y H:i') : null,
                'updated_at_human' => $this->updated_at ? $this->updated_at->diffForHumans() : null,
                'days_since_created' => $this->created_at ? $this->created_at->diffInDays(now()) : null,
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
            'Pending' => 'Pending Review',
            'Rented' => 'Currently Rented',
            'Sold' => 'Sold'
        ];

        return $labels[$this->property_state] ?? $this->property_state;
    }

    /**
     * Get formatted price string
     */
    private function getFormattedPrice(): string
    {
        $amount = number_format($this->price, 0);
        $currency = '$';
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
     * Get verification checklist for CS agents
     */
    private function getVerificationChecklist(): array
    {
        $checklist = [];

        // Basic information check
        $checklist['basic_info'] = [
            'label' => 'Basic Information',
            'items' => [
                [
                    'key' => 'title',
                    'label' => 'Property title provided',
                    'completed' => !empty($this->title),
                    'required' => true,
                ],
                [
                    'key' => 'description',
                    'label' => 'Adequate description (min 50 characters)',
                    'completed' => strlen($this->description ?? '') >= 50,
                    'required' => true,
                ],
                [
                    'key' => 'price',
                    'label' => 'Valid price set',
                    'completed' => $this->price > 0,
                    'required' => true,
                ],
                [
                    'key' => 'property_details',
                    'label' => 'Bedrooms and bathrooms specified',
                    'completed' => $this->bedrooms > 0 && $this->bathrooms > 0,
                    'required' => false,
                ],
            ]
        ];

        // Location verification
        $checklist['location'] = [
            'label' => 'Location Information',
            'items' => [
                [
                    'key' => 'address',
                    'label' => 'Complete address provided',
                    'completed' => !empty($this->location['address'] ?? null),
                    'required' => true,
                ],
                [
                    'key' => 'coordinates',
                    'label' => 'GPS coordinates available',
                    'completed' => !empty($this->location['latitude'] ?? $this->location['lat']),
                    'required' => false,
                ],
            ]
        ];

        // Media verification
        $hasImages = $this->relationLoaded('images') && $this->images->isNotEmpty();
        $checklist['media'] = [
            'label' => 'Property Media',
            'items' => [
                [
                    'key' => 'images',
                    'label' => 'Property images uploaded',
                    'completed' => $hasImages,
                    'required' => true,
                ],
                [
                    'key' => 'multiple_images',
                    'label' => 'Multiple images (recommended: 5+)',
                    'completed' => $hasImages && $this->images->count() >= 5,
                    'required' => false,
                ],
                [
                    'key' => 'primary_image',
                    'label' => 'Primary image set',
                    'completed' => $hasImages && $this->images->where('is_primary', true)->isNotEmpty(),
                    'required' => false,
                ],
            ]
        ];

        // Documents verification
        $hasDocuments = $this->relationLoaded('documents') && $this->documents->isNotEmpty();
        $checklist['documents'] = [
            'label' => 'Property Documents',
            'items' => [
                [
                    'key' => 'documents_uploaded',
                    'label' => 'Required documents uploaded',
                    'completed' => $hasDocuments,
                    'required' => false,
                ],
                [
                    'key' => 'documents_verified',
                    'label' => 'Documents verified',
                    'completed' => $hasDocuments && $this->documents->where('verified', true)->isNotEmpty(),
                    'required' => false,
                ],
            ]
        ];

        // Owner verification
        $checklist['owner'] = [
            'label' => 'Owner Information',
            'items' => [
                [
                    'key' => 'owner_assigned',
                    'label' => 'Property owner assigned',
                    'completed' => !empty($this->owner_id),
                    'required' => true,
                ],
                [
                    'key' => 'owner_active',
                    'label' => 'Owner account is active',
                    'completed' => $this->relationLoaded('owner') && $this->owner && $this->owner->status === 'active',
                    'required' => true,
                ],
                [
                    'key' => 'contact_info',
                    'label' => 'Owner contact information available',
                    'completed' => $this->relationLoaded('owner') && $this->owner && (!empty($this->owner->email) || !empty($this->owner->phone_number)),
                    'required' => true,
                ],
            ]
        ];

        return $checklist;
    }
}
