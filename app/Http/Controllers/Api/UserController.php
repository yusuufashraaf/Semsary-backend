<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\UserNotification;
use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Property;
use App\Models\Review;
use App\Models\Purchase;
use App\Models\Booking;
use App\Models\Wishlist;
use App\Models\PropertyPurchase;
use App\Models\RentRequest;
use App\Models\Notification;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

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
        return PropertyPurchase::with('property')->where('buyer_id', $id)->get();
    }

    public function bookings(int $id)
    {
        return RentRequest::with('property')->where('user_id', $id)->get();
    }

    public function wishlists(int $id)
    {
        return Wishlist::with(['property' => function($query) {
                $query->with('images');
            }, 'user'])
            ->where('user_id', $id)
            ->get()
            ->map(function($wishlist) {
                if ($wishlist->property->images->isNotEmpty()) {
                    $wishlist->property->image = $wishlist->property->images->first()->image_url;
                } else {
                    $wishlist->property->image = null;
                }
                unset($wishlist->property->images);
                return $wishlist;
            });
    }

    public function markAsRead(int $id, int $notificationId)
    {
        $notification = Notification::where('id', $notificationId)
            ->where('notifiable_id', $id)
            ->first();

        if (!$notification) {
            return response()->json([
                'message' => 'Notification not found'
            ], 404);
        }

        $notification->update(['read_at' => now()]);

        return response()->json([
            'message' => 'Notification marked as read',
            'notification' => $notification
        ]);
    }

    public function updateState($id, $status)
    {
        // Validate the status parameter
        $validStatuses = ['active', 'suspended', 'pending'];
        if (!in_array($status, $validStatuses)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Invalid status provided. Valid statuses: ' . implode(', ', $validStatuses)
            ], 400);
        }

        try {
            // Find the user
            $user = User::find($id);
            
            if (!$user) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'User not found'
                ], 404);
            }

            // Update the user status
            $user->status = $status;
            $user->save();

            // Log the action
            Log::info("User status updated", [
                'admin_id' => auth('api')->id(),
                'user_id' => $id,
                'old_status' => $user->getOriginal('status'),
                'new_status' => $status
            ]);

            return response()->json([
                'status' => 'success',
                'message' => 'User status updated successfully',
                'data' => [
                    'user_id' => $user->id,
                    'new_status' => $user->status,
                    'user_name' => $user->first_name . ' ' . $user->last_name
                ]
            ]);

        } catch (Exception $e) {
            Log::error("Error updating user status: " . $e->getMessage(), [
                'user_id' => $id,
                'status' => $status,
                'admin_id' => auth('api')->id()
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to update user status: ' . $e->getMessage()
            ], 502);
        }
    }
}