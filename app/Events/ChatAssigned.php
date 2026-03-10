<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ChatAssigned implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $chat_id;
    public $agent;

    public function __construct($chatId, $agent)
    {
        $this->chat_id = $chatId;
        $this->agent = $agent;
    }

    public function broadcastOn()
    {
        return new PrivateChannel('admin.chats.' . $this->agent->id);
    }

    public function broadcastWith()
    {
        return [
            'chat_id' => $this->chat_id,
            'agent' => $this->agent,
        ];
    }
}