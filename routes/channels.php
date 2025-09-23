<?php

use Illuminate\Support\Facades\Broadcast;


Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return true;
});

Broadcast::channel('chat', function () {
    return true; // Public channel - no auth required
});

Broadcast::channel('chat.{chatId}', function ($user, $chatId) {
    $chat = \App\Models\Chat::find($chatId);
    
    if (!$chat) {
        return false;
    }
    
    // Check if user is participant in this chat
    return $chat->owner_id === $user->id || $chat->renter_id === $user->id;
});

