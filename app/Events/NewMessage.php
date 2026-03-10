<?php

namespace App\Events;

use App\Models\Message;
use App\Models\Chat;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class NewMessage implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $message;
    public $chat;

    public function __construct(Message $message, Chat $chat)
    {
        $this->message = $message;
        $this->chat = $chat;

        // This ensures the event is broadcast to the correct channel
        $this->dontBroadcastToCurrentUser();
    }

    public function broadcastOn()
{
    return new PrivateChannel('chat.' . $this->chat->id);
}

    public function broadcastWith()
    {
        return [
            'message' => [
                'id' => $this->message->id,
                'chat_id' => $this->message->chat_id,
                'sender_id' => $this->message->sender_id,
                'content' => $this->message->content,
                'is_read' => $this->message->is_read,
                'created_at' => $this->message->created_at,
                'updated_at' => $this->message->updated_at,
                'sender' => $this->message->sender,
            ],
            'chat_id' => $this->chat->id
        ];
    }

    public function broadcastAs()
    {
        return 'new.message';
    }
}