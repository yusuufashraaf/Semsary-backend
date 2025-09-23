<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PropertyResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'description' => $this->description,
            'type' => $this->type,
            'price' => (float) $this->price,
            'price_type' => $this->price_type,
            'bedrooms' => $this->bedrooms,
            'bathrooms' => $this->bathrooms,
            'sqft' => $this->size,
            'status' => $this->property_state,

            // Location JSON casted to array
            'coordinates' => [
                'lat' => $this->location['latitude'] ?? null,
                'lng' => $this->location['longitude'] ?? null,
            ],
            'address' => $this->location['address'] ?? null,
            'city' => $this->location['city'] ?? null,
            'state' => $this->location['state'] ?? null,
            'zip_code' => $this->location['zip_code'] ?? null,

            // Relations
            'images' => $this->images->pluck('image_url'),
            'amenities' => $this->features->pluck('name'),

            // Owner (host)
            'host' => $this->owner ? [
                'id' => $this->owner->id,
                'name' => trim($this->owner?->first_name . ' ' . $this->owner?->last_name),
                'avatar' => strtoupper(substr($this->owner->first_name, 0, 1) . substr($this->owner->last_name, 0, 1)),
                'joinDate' => optional($this->owner->created_at)->format('Y'),
                    'email' => $this->owner->email,
    'phone' => $this->owner->phone_number ?? null,

            ] : null,

            // Optional extras
            'rating' => round($this->reviews->avg('rating') ?? 0, 1),
            'reviewCount' => $this->reviews->count(),
        ];
    }
}