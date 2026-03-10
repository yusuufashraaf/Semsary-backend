<?php

namespace App\Events;

use App\Models\Property;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PropertyUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public Property $property;

    public function __construct(Property $property)
    {
        // Always eager load relations you want to broadcast
        $this->property = $property->fresh(['owner', 'images']);
    }

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("user.{$this->property->owner_id}")
        ];
    }

    public function broadcastAs(): string
    {
        return "property.updated";
    }

    public function broadcastWith(): array
    {
        return [
            "id" => $this->property->id,
            "title" => $this->property->title,
            "property_state" => $this->property->property_state,
        ];
    }
}
