<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Property;
use App\Models\Review;
use App\Models\Notification;
use App\Models\Purchase;
use App\Models\Booking;

class Profile_Controller extends Controller
{
    public function index(int $id)
    {
        return User::with('properties','reviews','notifications')->find($id);
    }

    public function reviews(int $id)
    {
        return Review::with('property','customer')->where('user_id',$id)->get();
    }

    public function properties(int $id)
    {
        return Property::where('owner_id',$id)->get();
    }

    public function notifications(int $id)
    {

        return Notification::where('user_id',$id)->get();
    }

    public function purchases(int $id)
    {

        return Purchase::with('property')->where('user_id',$id)->get();
    }

    public function bookings(int $id)
    {

        return Booking::with('property')->where('user_id',$id)->get();
    }
}
?>