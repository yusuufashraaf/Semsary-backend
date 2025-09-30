<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Events\MessageSent;
use App\Models\Message;
use App\Models\Purchase;
use App\Models\Chat;
use App\Models\User;
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

    // public function fetchChats($userId)
    // {

    //     $user = User::find("id",$userId);
    //     if($user->role == "agent"){
    //         $chats = Chat::with(['latestMessage', 'property', 'owner', 'renter'])
    //         ->where('owner_id', $userId)
    //         ->orWhere('renter_id', $userId) 
    //         ->get();
    //     }
    //     else{
    //         $chats = Chat::with(['latestMessage', 'property', 'owner', 'renter'])
    //         ->where('owner_id', $userId)
    //         ->get();
    //     }

    //     return response()->json([
    //         'chats' => $chats,
    //         'total_unread' => $chats->sum('unread_count')
    //     ]);
    // }

    public function fetchAvailableChats($userId)
{
    // Get completed purchases
    $rentRequests = Purchase::where("user_id", $userId)->get();

    // Create missing chats - using current user as both owner and renter initially
    foreach ($rentRequests as $rentRequest) {
        $existingChat = Chat::where('property_id', $rentRequest->property_id)
            ->where('owner_id', $userId) // Check if user already has a chat for this property
            ->first();
        
        if (!$existingChat) {
            Chat::create([
                'property_id' => $rentRequest->property_id,
                'owner_id' => $userId, // Current user as owner temporarily
                'renter_id' => $userId, // Current user as renter temporarily
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    $user = User::find($userId); // Fixed: removed "id" parameter
    
    if($user->role == "agent"){
        $chats = Chat::with(['latestMessage', 'property', 'owner', 'renter','property.owner'])
            ->where('owner_id', $userId)
            ->orWhere('renter_id', $userId) 
            ->get();
    } else {
        $chats = Chat::with(['latestMessage', 'property', 'owner', 'renter'])
            ->where('owner_id', $userId)
            ->get();
    }

    return response()->json([
        'chats' => $chats,
        'total_unread' => $chats->sum('unread_count')
        //'rent_requests' => $rentRequests
    ]);
}

    public function deleteChat(int $chatId)
{
    try {
        // Find the chat first to ensure it exists
        $chat = Chat::findOrFail($chatId);
        
        // Delete all messages associated with this chat
        Message::where('chat_id', $chatId)->delete();
        
        // Delete the chat itself
        $chat->delete();
        
        return response()->json([
            'status' => 'success',
            'message' => 'Chat and all associated messages have been deleted successfully'
        ]);
        
    } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
        return response()->json([
            'status' => 'error',
            'message' => 'Chat not found'
        ], 404);
        
    } catch (\Exception $e) {
        return response()->json([
            'status' => 'error',
            'message' => 'Failed to delete chat: ' . $e->getMessage()
        ], 500);
    }
}
}