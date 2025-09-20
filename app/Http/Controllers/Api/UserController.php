<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\UserNotification;
use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Property;
use App\Models\Review;
use App\Models\Notification;
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
        return Property::where('owner_id', $id)->get();
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
        return Wishlist::with('property', 'user')->where('user_id', $id)->get();
    }
}