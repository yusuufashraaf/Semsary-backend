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

        if ($request->filled('status')) {
            $query->where('property_state', $request->status);
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

        // Sorting
        if ($request->filled('sortBy')) {
            $sortField = $request->sortBy;
            $sortOrder = $request->get('sortOrder', 'asc');
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
                'image' => $property->images->first()->image_url ?? null,
                'title' => $property->title,
                'bedrooms' => $property->bedrooms ?? null,
                'bathrooms' => $property->bathrooms ?? null,
                'sqft' => $property->size,
                'price' => (float) $property->price,
                'status' => strtolower($property->property_state),
            ];
        });

        // Replace collection with transformed listings
        $properties->setCollection($listings);

        return response()->json($properties);
    }
}