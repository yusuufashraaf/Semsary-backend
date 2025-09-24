<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Events\MessageSent;
use App\Models\Message;
use App\Models\Chat;
use App\Models\RentRequest;

class NewMessageController extends Controller
{
    public function sendMessage(Request $request)
    {
        $message = $request->input('content');
        $userId = $request->input('sender_id');
        $chatId = $request->input('chat_id');

        $newMessage = Message::create([
            'chat_id' => $chatId,
            'sender_id' => $userId,
            'content' => $message
        ]);
        
        broadcast(new MessageSent($newMessage));
        
        return response()->json([
            'status' => 'sent',
            'message' => $newMessage
        ]);
    }

    public function fetchMessages($chatId)
    {
        // FIX: Return consistent JSON structure
        $messages = Message::where('chat_id', $chatId)->get();
        
        return response()->json([
            'messages' => $messages
        ]);
    }

    public function fetchChats($userId)
    {
        $chats = Chat::with(['latestMessage', 'property', 'owner', 'renter'])
            ->where('owner_id', $userId)
            ->orWhere('renter_id', $userId) 
            ->get();

        return response()->json([
            'chats' => $chats,
            'total_unread' => $chats->sum('unread_count')
        ]);
    }

    public function fetchAvailableChats($userId)
    {
// Get completed/paid rent requests
$rentRequests = RentRequest::where('user_id', $userId)
    ->whereIn('status', ['completed', 'paid'])
    ->get();

// Create missing chats
foreach ($rentRequests as $rentRequest) {
    $existingChat = Chat::where('property_id', $rentRequest->property_id)
        ->where(function($query) use ($userId) {
            $query->where('owner_id', $userId)
                  ->orWhere('renter_id', $userId);
        })
        ->first();
    
    if (!$existingChat) {
        Chat::create([
            'property_id' => $rentRequest->property_id,
            'owner_id' => $rentRequest->property->owner_id, // assuming relation
            'renter_id' => $userId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}

// Return all chats
$chats = Chat::with(['latestMessage', 'property', 'owner', 'renter'])
    ->where('owner_id', $userId)
    ->orWhere('renter_id', $userId)
    ->get();

return response()->json([
    'chats' => $chats,
    'rent_requests' => $rentRequests
]);
    }
}