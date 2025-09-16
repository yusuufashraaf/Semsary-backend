<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PropertyList;
use App\Models\Feature;
use Illuminate\Support\Facades\Cache;

class FiltersController extends Controller
{
    public function index()
    {
        // Cache results for 1 hour (3600s) to reduce repeated queries
        return Cache::remember('filters', 3600, function () {
            // Unique property types
            $propertyTypes = PropertyList::query()
                ->distinct()
                ->pluck('type')
                ->filter()
                ->values();

            // Unique statuses
            $statuses = PropertyList::query()
                ->distinct()
                ->pluck('property_state')
                ->filter()
                ->values();

            // Unique bedroom counts (stored in location JSON)
            $bedroomsOptions = PropertyList::query()
                ->distinct()
                ->pluck('bedrooms')
                ->filter()
                ->unique()
                ->sort()
                ->values();

            // Unique locations (city)
            $locations = PropertyList::query()
                ->selectRaw('DISTINCT JSON_UNQUOTE(JSON_EXTRACT(location, "$.city")) as city')
                ->pluck('city')
                ->filter()
                ->unique()
                ->sort()
                ->values();

            // Amenities from features table
            $amenitiesOptions = Feature::pluck('name')->filter()->unique()->values();

            // Min and Max price (default fallback if no data)
            $minPrice = PropertyList::min('price') ?? 0;
            $maxPrice = PropertyList::max('price') ?? 0;

            return [
                'locations' => $locations,
                'propertyTypes' => $propertyTypes,
                'bedroomsOptions' => $bedroomsOptions,
                'statuses' => $statuses,
                'amenitiesOptions' => $amenitiesOptions,
                'priceMin' => $minPrice,
                'priceMax' => $maxPrice,
            ];
        });
    }
}