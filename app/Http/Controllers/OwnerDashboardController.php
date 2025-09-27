<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\Property;
use App\Models\PropertyPurchase;
use App\Models\Purchase;
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
    public function stats(){
        $user = auth('api')->user();

        $total_properties = Property::where('owner_id', $user->id)->count();

        $bookingsCount = Purchase::whereHas('property', function ($q) use ($user) {
            $q->where('owner_id', $user->id);
        })->where('status', 'successful') ->count();


        $salesIncome = PropertyPurchase::where('seller_id', $user->id)
            ->where('status', 'paid')
            ->sum('amount');

        $rentIncome = Purchase::whereHas('property', function ($q) use ($user) {
            $q->where('owner_id', $user->id);
        })->where('status', 'successful')
          ->sum('amount');

        $totalIncome = $salesIncome + $rentIncome;
        
        $boughtProperties = PropertyPurchase::with('property')
            ->where('buyer_id', $user->id)
            ->get();

        $rentedProperties = Purchase::with('property')
            ->where('user_id', $user->id)
            ->get();

        return response()->json([
            'success' => true,
            'data' => [
                'total_properties'=>$total_properties,
                'bookings_count' => $bookingsCount,
                'sales_income'   => $salesIncome,
                'rent_income'    => $rentIncome,
                'total_income'   => $totalIncome,
                'bought_properties' => $boughtProperties,
                'rented_properties' => $rentedProperties,
            ]
        ]);
    }
}
