<?php

namespace App\Http\Controllers;

use App\Models\Chat;
use App\Models\Message;
use App\Models\Property;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
USE App\Events\NewMessage;
USE App\Events\MessageRead;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Broadcast;

class MessageController extends Controller
{
    public function getUserChats()
    {
        $user = auth('api')->user();
        if($user->role == "user"){
            $chats = Chat::with(['latestMessage', 'property', 'owner', 'renter'])
            ->where('owner_id', $user->id)
            ->get();

        return response()->json([
            'chats' => $chats,
            'total_unread' => $chats->sum('unread_count')
        ]);
        }
        else{
            $chats = Chat::with(['latestMessage', 'property', 'owner', 'renter'])
            ->Where('renter_id', $user->id)
            ->get();

        return response()->json([
            'chats' => $chats,
            'total_unread' => $chats->sum('unread_count')
        ]);
        }
    }

    public function getChatMessages(Chat $chat)
    {
        $user = auth('api')->user();
        
        // Check if user is participant in this chat
        if ($chat->owner_id !== $user->id && $chat->renter_id !== $user->id) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $messages = $chat->messages()->orderBy('created_at', 'desc')->get();

        // Mark messages as read
        $chat->messages()
            ->where('sender_id', '!=', $user->id)
            ->where('is_read', false)
            ->update(['is_read' => true]);

        return response()->json([
            'messages' => $messages,
            'chat' => $chat
        ]);
    }

     public function sendMessage(Request $request, Chat $chat)
    {
        $user = auth('api')->user();
        
        // Check if user is participant in this chat
        if ($chat->owner_id !== $user->id && $chat->renter_id !== $user->id) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $validator = Validator::make($request->all(), [
            'content' => 'required|string|max:1000'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $message = $chat->messages()->create([
            'sender_id' => $user->id,
            'content' => $request->content
        ]);

        // Load the sender relationship
        $message->load('sender');

        // Update last message timestamp
        $chat->update(['last_message_at' => now()]);

        // Broadcast the new message (without ->toOthers() for now to test)
        broadcast(new NewMessage($message, $chat));

        return response()->json([
            'message' => $message,
            'chat' => $chat->fresh()
        ]);
    }

    public function markAsRead(Chat $chat)
    {
        $user = auth('api')->user();
        
        // Check if user is participant in this chat
        if ($chat->owner_id !== $user->id && $chat->renter_id !== $user->id) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        try {
            // Update unread messages for this user
            $updatedCount = $chat->messages()
                ->where('sender_id', '!=', $user->id)
                ->where('is_read', false)
                ->update(['is_read' => true]);

            // Update chat's unread count if your model has this field
            if (method_exists($chat, 'updateUnreadCount')) {
                $chat->updateUnreadCount();
            }

            // Broadcast read receipt
            broadcast(new MessageRead($chat, $user->id));

            return response()->json([
                'success' => true,
                'read_count' => $updatedCount
            ]);
            
        } catch (\Exception $e) {
            \Log::error('Error marking messages as read: ' . $e->getMessage());
            return response()->json(['error' => 'Server error'], 500);
        }
    }

    public function authenticateBroadcast(Request $request)
{
    $user = auth('api')->user();
    
    if (!$user) {
        return response()->json(['error' => 'Unauthenticated'], 401);
    }

    $socketId = $request->socket_id;
    $channelName = $request->channel_name;

    // Check if it's a private chat channel
    if (preg_match('/^private-chat\.(\d+)$/', $channelName, $matches)) {
        $chatId = $matches[1];
        $chat = Chat::find($chatId);
        
        if ($chat && ($chat->owner_id === $user->id || $chat->renter_id === $user->id)) {
            return response()->json([
                'auth' => $user->id . ':' . $channelName,
            ]);
        }
    }
    
    return response()->json(['error' => 'Access denied'], 403);
}

    public function startChat(Request $request)
    {
        $user = auth('api')->user();
        
        $validator = Validator::make($request->all(), [
            'property_id' => 'required|exists:properties,id',
            'owner_id' => 'required|exists:users,id',
            'renter_id' => 'required|exists:users,id'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // Verify property ownership
        $property = Property::find($request->property_id);
        if ($property->user_id !== $request->owner_id) {
            return response()->json(['error' => 'Invalid property ownership'], 422);
        }

        // Check if user is either owner or renter
        if ($user->id !== $request->owner_id && $user->id !== $request->renter_id) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        // Find existing chat or create new one
        $chat = Chat::firstOrCreate([
            'property_id' => $request->property_id,
            'owner_id' => $request->owner_id,
            'renter_id' => $request->renter_id
        ]);

        return response()->json([
            'chat' => $chat->load('property', 'owner', 'renter')
        ]);
    }
}