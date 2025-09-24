<?php

namespace App\Http\Controllers;

use App\Models\Review;
use App\Http\Resources\ReviewResource;
use Illuminate\Http\Request;
use App\Models\Property;
use App\Models\rentrequest;
use Illuminate\Support\Facades\Validator;

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

    public function getReviewableProperties()
{
    $user = auth('api')->user();
    
    // Get properties that the user owns and hasn't reviewed yet

    //$userId = 50; // Or get from auth: auth('api')->user()->id
    
    // Get properties from completed rent requests
    $rentRequests = RentRequest::with('property')
        ->where('user_id', $user->id)
        ->where('status', 'completed')
        ->get();

    // Extract property IDs from the rent requests
    $propertyIds = $rentRequests->pluck('property_id')->filter()->unique();

    if ($propertyIds->isEmpty()) {
        return response()->json([
            'properties' => []
        ]);
    }

    // Get properties that don't have a review from this user
    $reviewableProperties = Property::whereIn('id', $propertyIds)
        ->whereDoesntHave('reviews', function ($query) use ($userId) {
            $query->where('user_id', $userId);
        })
        ->get();

    return response()->json([
        'properties' => $reviewableProperties,
    ]);
}

    public function store(Request $request)
{
    $user = auth('api')->user();

    // Validate the request data
    $validator = Validator::make($request->all(), [
        'property_id' => 'required|integer|exists:properties,id',
        'comment' => 'required|string|min:5|max:1000',
        'rating' => 'required|integer|min:1|max:5'
    ]);

    if ($validator->fails()) {
        return response()->json(['errors' => $validator->errors()], 422);
    }

    // Check if user has already reviewed this property
    $existingReview = Review::where('property_id', $request->property_id)
        ->where('user_id', $user->id)
        ->first();

    if ($existingReview) {
        return response()->json([
            'message' => 'You have already reviewed this property'
        ], 422);
    }

    // BEST PRACTICE: Explicitly specify all fields
    $review = Review::create([
        'property_id' => $request->property_id,
        'user_id' => $user->id,
        'comment' => $request->comment,
        'rating' => $request->rating
    ]);

    // Load relationships
    $review->load(['property', 'user']);

    return response()->json([
        'message' => 'Review submitted successfully',
        'review' => $review
    ], 201);
}

    public function update(Request $request, Review $review)
    {
        $user = auth('api')->user();
        
        if ($review->user_id !== $user->id) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $validator = Validator::make($request->all(), [
            'comment' => 'sometimes|required|string|min:10|max:1000',
            'rating' => 'sometimes|required|integer|min:1|max:5'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $review->update($request->only(['comment', 'rating']));
        $review->load(['property', 'user']);

        return response()->json([
            'message' => 'Review updated successfully',
            'review' => $review
        ]);
    }

    public function destroy(Review $review)
    {
        $user = auth('api')->user();
        
        if ($review->user_id !== $user->id) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $review->delete();

        return response()->json([
            'message' => 'Review deleted successfully'
        ]);
    }

    public function getPropertyReviews(Property $property)
    {
        $reviews = Review::with(['property', 'user'])
            ->where('property_id', $property->id)
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'reviews' => $reviews
        ]);
    }

    public function getUserReviews($userId)
    {
        $reviews = Review::with(['property', 'user'])
            ->where('user_id', $userId)
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'reviews' => $reviews
        ]);
    }
}