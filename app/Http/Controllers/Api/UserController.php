<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\UserNotification;
use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Property;
use App\Models\Review;
//use App\Models\Notification;
use App\Models\Purchase;
use App\Models\Booking;
use App\Models\Wishlist;

class UserController extends Controller
{
    public function index(int $id)
    {
        return User::with('properties', 'reviews', 'notifications')->find($id);
    }

    public function reviews(int $id)
    {
        return Review::with('property', 'user')->where('user_id', $id)->get();
    }

   public function properties(int $id)
{
    return Property::with('images')
        ->where('owner_id', $id)
        ->get()
        ->each(function($property) {
            $property->image = optional($property->images->first())->image_url;
            unset($property->images);
        });
}

    public function notifications(int $id)
    {

        return UserNotification::where('user_id', $id)->get();
    }

    public function purchases(int $id)
    {

        return Purchase::with('property')->where('user_id', $id)->get();
    }

    public function bookings(int $id)
    {

        return Booking::with('property')->where('user_id', $id)->get();
    }

    public function wishlists(int $id)
{
    return Wishlist::with(['property' => function($query) {
            $query->with('images');
        }, 'user'])
        ->where('user_id', $id)
        ->get()
        ->map(function($wishlist) {
            // Check if images exist and get the first one's URL
            if ($wishlist->property->images->isNotEmpty()) {
                $wishlist->property->image = $wishlist->property->images->first()->image_url;
            } else {
                $wishlist->property->image = null;
            }
            unset($wishlist->property->images);
            return $wishlist;
        });
}

public function markAsRead(int $id,int $notificationid)
    {
        // Verify the notification belongs to the authenticated user
        $notification = UserNotification::where('id', $notificationid)
            ->where('user_id', $id)
            ->firstOrFail();

        $notification->update(['is_read' => !$notification->is_read]);

        return response()->json([
            'message' => 'Notification marked as read',
            'notification' => $notification
        ]);
    } 


}