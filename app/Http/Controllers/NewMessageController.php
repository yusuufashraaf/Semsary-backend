<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Events\MessageSent;
use App\Models\Message;
use App\Models\Chat;

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
}