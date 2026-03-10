<?php

namespace App\Events;

use App\Models\Chat;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MessageRead implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $chat;
    public $userId;

    public function __construct(Chat $chat, $userId)
    {
        $this->chat = $chat;
        $this->userId = $userId;
    }

    public function broadcastOn()
{
    return new PrivateChannel('chat.' . $this->chat->id);
}

    public function broadcastWith()
    {
        return [
            'chat_id' => $this->chat->id,
            'read_by' => $this->userId,
            'unread_count' => $this->chat->unread_count
        ];
    }
}