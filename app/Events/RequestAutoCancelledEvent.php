<?php
namespace App\Events;

use App\Models\RentRequest;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class RequestAutoCancelledEvent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $rentRequest;

    public function __construct(RentRequest $rentRequest)
    {
        $this->rentRequest = $rentRequest;
    }

    public function broadcastOn()
    {
        // Private channel for user notifications
        return new PrivateChannel('user.' . $this->rentRequest->user_id);
    }

    public function broadcastWith()
    {
        return [
            'message' => 'Your booking request has been cancelled due to an expired payment deadline.',
            'rent_request_id' => $this->rentRequest->id,
        ];
    }
}
