<?php

// namespace App\Events;

// use Illuminate\Broadcasting\Channel;
// use Illuminate\Broadcasting\InteractsWithSockets;
// use Illuminate\Broadcasting\PresenceChannel;
// use Illuminate\Broadcasting\PrivateChannel;
// use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
// use Illuminate\Foundation\Events\Dispatchable;
// use Illuminate\Queue\SerializesModels;
// use App\Models\Message;


// class MessageSent implements ShouldBroadcast
// {
//     use Dispatchable, InteractsWithSockets, SerializesModels;

//     public $message;

//     public function __construct(Message $message)
//     {
//         $this->message = $message->load('sender'); // Eager load sender relationship
//     }

//     public function broadcastOn()
//     {
//         return new Channel('chat.' . $this->message->chat_id);
//     }

//     public function broadcastAs()
//     {
//         return 'new.message'; // Consistent event name
//     }

//     public function broadcastWith()
//     {
//         return [
//             'message' => $this->message
//         ];
//     }
// }
namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;

class MessageSent implements ShouldBroadcast
{
    public $message;

    public function __construct($message)
    {
        $this->message = $message;
    }

    public function broadcastOn()
    {
        return new Channel('chat');
    }

    // ADD THIS METHOD to fix the event name
    public function broadcastAs()
    {
        return 'MessageSent'; // Make sure this matches frontend
    }

    public function broadcastWith()
    {
        return [
            'message' => $this->message,
            'timestamp' => now()->toDateTimeString()
        ];
    }
}