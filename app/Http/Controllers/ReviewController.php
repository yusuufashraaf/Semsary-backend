<?php

namespace App\Http\Controllers;

use App\Models\Review;
use App\Http\Resources\ReviewResource;

class ReviewController extends Controller
{
    /**
     * Get reviews for a given property (paginated).
     */
    public function index($propertyId)
    {
        $reviews = Review::with('user')
            ->where('property_id', $propertyId)
            ->latest()
            ->paginate(3);

        return ReviewResource::collection($reviews);
    }
}