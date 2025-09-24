<?php
namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use App\Models\Message; // Fixed namespace

class MessageSent implements ShouldBroadcast
{
    use Dispatchable, SerializesModels;

    public $message;
    public $chatId;

    public function __construct($message) // Better parameter name
    {
        $this->message = $message;
        $this->chatId = $message->chat_id; // Fixed: should be chat_id, not chatid
    }

    public function broadcastOn()
    {
        // Fixed: use string concatenation with dots, not plus sign
        return new Channel('chat.' . $this->chatId);
    }

    public function broadcastAs()
    {
        return 'MessageSent';
    }

    // Add this method to specify what data to broadcast
    public function broadcastWith()
    {
        return [
            'message' => $this->message,
            'timestamp' => now()->toDateTimeString()
        ];
    }
}