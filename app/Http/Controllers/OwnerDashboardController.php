<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\Property;
use App\Models\Review;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class OwnerDashboardController extends Controller
{
    public function index(): JsonResponse
    {
        $ownerId = auth()->id();

        // caching for 1 minute
        $data = cache()->remember("owner_dashboard_$ownerId", 60, function () use ($ownerId) {
            return [
                'total_properties' => Property::where('owner_id', $ownerId)->count(),
                'total_bookings'   => Booking::forOwner($ownerId)->count(),
                'total_income'     => Booking::forOwner($ownerId)
                                            ->where('status', 'confirmed')
                                            ->sum('total_price'),
                'total_reviews'    => Review::forOwner($ownerId)->count(),
                'average_rating'   => round(Review::forOwner($ownerId)->avg('rating'), 2),
            ];
        });

        return response()->json([
            'message' => 'Owner Dashboard Data',
            'data'    => $data,
        ]);
    }
}
