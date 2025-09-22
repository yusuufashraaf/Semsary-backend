<?php

namespace App\Http\Controllers;

use App\Models\Chat;
use App\Models\Message;
use App\Models\Property;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class MessageController extends Controller
{
    public function getUserChats()
    {
        $user = auth('api')->user();
        
        $chats = Chat::with(['latestMessage', 'property', 'owner', 'renter'])
            ->where('owner_id', $user->id)
            ->orWhere('renter_id', $user->id)
            ->get();

        return response()->json([
            'chats' => $chats,
            'total_unread' => $chats->sum('unread_count')
        ]);
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

        // Update last message timestamp
        $chat->update(['last_message_at' => now()]);

        return response()->json([
            'message' => $message->load('sender'),
            'chat' => $chat->fresh()
        ]);
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

    public function markAsRead(Chat $chat)
    {
        $user = auth('api')->user();
        
        if ($chat->owner_id !== $user->id && $chat->renter_id !== $user->id) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $chat->messages()
            ->where('sender_id', '!=', $user->id)
            ->where('is_read', false)
            ->update(['is_read' => true]);

        return response()->json(['success' => true]);
    }
}