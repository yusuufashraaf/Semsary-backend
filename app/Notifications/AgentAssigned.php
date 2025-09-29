<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;

class AgentAssigned extends Notification implements ShouldQueue
{
    use Queueable;

    protected $data;

    public function __construct($chat, $property = null)
    {
        $this->data = [
            'id' => $chat->id,
            'chat_id' => $chat->id,
            'property_title' => $property ? $property->title : 'Unknown Property',
            'property_id' => $property ? $property->id : null,
            'assigned_at' => now()->toDateTimeString(),
        ];
    }

    public function via($notifiable)
    {
        return ['database', 'broadcast'];
    }

    public function toDatabase($notifiable)
    {
        return [
            'id' => $this->data['id'],
            'chat_id' => $this->data['chat_id'],
            'property_title' => $this->data['property_title'],
            'message' => 'You have been assigned to chat for property: ' . $this->data['property_title'],
        ];
    }

    public function toBroadcast($notifiable)
    {
        return new BroadcastMessage([
            'id' => $this->data['id'],
            'chat_id' => $this->data['chat_id'],
            'property_title' => $this->data['property_title'],
            'property_id' => $this->data['property_id'],
            'message' => 'New chat assignment: ' . $this->data['property_title'],
            'type' => 'agent_assignment',
        ]);
    }
}