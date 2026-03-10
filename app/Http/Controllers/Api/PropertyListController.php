<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PropertyList;
use Illuminate\Http\Request;

class PropertyListController extends Controller
{
    /**
     * Get all properties with pagination, sorting, search, and filters
     */
    public function index(Request $request)
    {
        $query = PropertyList::with(['features', 'images']);

        // Multiple ways to exclude current user's properties
        $currentUserId = null;

        // Method 1: Check if user is authenticated via Laravel auth
        if (auth()->check()) {
            $currentUserId = auth()->id();
        }
        
        // Method 2: Check if user_id is passed in request (fallback)
        elseif ($request->filled('user_id')) {
            $currentUserId = $request->user_id;
        }
        
        // Method 3: Check Authorization header for Bearer token
        elseif ($request->bearerToken()) {
            // If using Sanctum or Passport, try to get user from token
            $user = auth('sanctum')->user() ?? auth('api')->user();
            if ($user) {
                $currentUserId = $user->id;
            }
        }

        // Apply the filter if we found a user ID
        if ($currentUserId) {
            $query->where('owner_id', '!=', $currentUserId);
        }

        // Debug: Log what we found (remove this in production)
        \Log::info('Property List Debug', [
            'auth_check' => auth()->check(),
            'auth_id' => auth()->id(),
            'request_user_id' => $request->get('user_id'),
            'current_user_id' => $currentUserId,
            'bearer_token' => $request->bearerToken() ? 'present' : 'missing'
        ]);

        // Search by title, description, or city
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%$search%")
                    ->orWhere('description', 'like', "%$search%")
                    ->orWhere('location->city', 'like', "%$search%");
            });
        }

        // Filtering
        if ($request->filled('location')) {
            $query->where('location->city', $request->location);
        }

        if ($request->filled('propertyType') || $request->filled('type')) {
            $type = $request->get('propertyType', $request->get('type'));
            $query->whereRaw('LOWER(type) = ?', [strtolower($type)]);
        }

        // Status filter - Updated to handle multiple statuses
        // Always filter by allowed statuses first
        $query->whereIn('property_state', ['Valid', 'Invalid']);

        // Then apply user's specific status filter if provided AND valid
        if ($request->filled('status')) {
            $status = $request->status;
            
            // Convert to array if needed
            if (is_string($status) && strpos($status, ',') !== false) {
                $statuses = array_map('trim', explode(',', $status));
            } else if (is_array($status)) {
                $statuses = $status;
            } else {
                $statuses = [$status];
            }
            
            // Only apply user filter if it contains valid statuses
            $allowedStatuses = ['Valid', 'Invalid'];
            $validStatuses = array_intersect($statuses, $allowedStatuses);
            
            // Override base filter only if user selected valid statuses
            if (!empty($validStatuses)) {
                $query->whereIn('property_state', $validStatuses);
            }
        }

        if ($request->filled('beds')) {
            $beds = explode(',', $request->beds);
            $beds = array_map('intval', $beds);
            $query->whereIn('bedrooms', $beds);
        }

        if ($request->filled('priceMin')) {
            $query->where('price', '>=', $request->priceMin);
        }
        
        // Price Type filter
        if ($request->filled('price_type')) {
            $query->where('price_type', $request->price_type);
        }

        if ($request->filled('priceMax')) {
            $query->where('price', '<=', $request->priceMax);
        }
        
        if ($request->filled('amenities')) {
            $amenities = is_array($request->amenities)
                ? $request->amenities
                : explode(',', $request->amenities); // fallback if it's a string

            foreach ($amenities as $amenity) {
                $query->whereHas('features', function ($q) use ($amenity) {
                    $q->whereRaw('LOWER(name) = ?', [strtolower(trim($amenity))]);
                });
            }
        }

        // Sorting
        if ($request->filled('sortBy')) {
            $sortField = $request->sortBy;
            $sortOrder = strtolower($request->get('sortOrder', 'asc'));
            if (in_array($sortField, ['price', 'created_at', 'size'])) {
                $query->orderBy($sortField, $sortOrder);
            }
        } else {
            $query->latest();
        }

        // Pagination
        $perPage = $request->get('per_page', 10);
        $properties = $query->paginate($perPage);

        // Transform into Listing[] format
        $listings = $properties->getCollection()->map(function ($property) {
            return [
                'id' => (string) $property->id,
                'owner_id'=> $property->owner_id,
                'image' => $property->images->first()->image_url ?? null,
                'title' => $property->title,
                'bedrooms' => $property->bedrooms ?? null,
                'bathrooms' => $property->bathrooms ?? null,
                'sqft' => $property->size,
                'price' => (float) $property->price,
                'status' => strtolower($property->property_state),
                'price_type' => $property->price_type
            ];
        });

        // Replace collection with transformed listings
        $properties->setCollection($listings);

        return response()->json($properties);
    }
}