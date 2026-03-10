<?php

namespace App\Http\Controllers;

use App\Models\UserNotification;
use App\Models\RentRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class UserNotificationController extends Controller
{
    public function getUserNotifications(Request $request)
    {
        $user = Auth::user();
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'You must be logged in to view notifications.',
            ], 401);
        }

        // Paginate notifications
        $notifications = UserNotification::where('user_id', $user->id)
            ->latest()
            ->paginate($request->get('per_page', 10));

        $total = UserNotification::where('user_id', $user->id)->count();

        // Transform notifications
        $notifications->getCollection()->transform(function ($notification) {
            $purpose = $notification->purpose;

            // Base structure
            $data = [
                'id' => $notification->id,
                'purpose' => $purpose->value,
                'label' => $purpose->label(),
                'requires_action' => $purpose->requiresAction(),
                'priority' => $purpose->priority(),
                'title' => $notification->title,
                'body' => $notification->body,
                'is_read' => (bool) $notification->is_read,
                'created_at' => $notification->created_at,
            ];

            // If it's rent-related, attach property
            if (in_array($purpose, \App\Enums\NotificationPurpose::getByCategory()['rent_requests'])) {
                $rentRequest = RentRequest::with('property')->find($notification->entity_id);

                if ($rentRequest && $rentRequest->property) {
                    $data['property'] = [
                        'id' => $rentRequest->property->id,
                        'title' => $rentRequest->property->title,
                        'price_per_night' => $rentRequest->property->price,
                        'owner_id' => $rentRequest->property->owner_id,
                    ];
                }
            }

            return $data;
        });

        return response()->json([
            'success' => true,
            'total' => $total,
            'notifications' => $notifications,
        ]);
    }
}