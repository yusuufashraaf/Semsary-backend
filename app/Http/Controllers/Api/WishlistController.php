<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Wishlist;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class WishlistController extends Controller
{
    public function index()
    {
        try {
            $wishlist = Wishlist::with('property')
                ->where('user_id', Auth::id())
                ->get();

            return response()->json([
                'success' => true,
                'message' => 'Wishlist fetched successfully.',
                'data' => $wishlist,
            ]);
        } catch (\Exception $e) {
            Log::error("Wishlist index error: " . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while fetching wishlist.',
            ], 500);
        }
    }

    public function store(Request $request)
    {
        $request->validate([
            'property_id' => 'required|exists:properties,id',
        ]);

        try {
            $wishlist = Wishlist::firstOrCreate([
                'user_id' => Auth::id(),
                'property_id' => $request->property_id,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Property added to wishlist successfully.',
                'data' => $wishlist,
            ], 201);
        } catch (\Exception $e) {
            Log::error("Wishlist store error: " . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while adding to wishlist.',
            ], 500);
        }
    }

    public function destroy($propertyId)
    {
        try {
            Wishlist::where('user_id', Auth::id())
                ->where('property_id', $propertyId)
                ->delete();

            return response()->json([
                'success' => true,
                'message' => 'Property removed from wishlist successfully.',
            ]);
        } catch (\Exception $e) {
            Log::error("Wishlist destroy error: " . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while removing from wishlist.',
            ], 500);
        }
    }
}