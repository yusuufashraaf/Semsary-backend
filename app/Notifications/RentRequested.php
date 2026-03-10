<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class RentRequested extends Notification implements ShouldQueue
{
    use Queueable;

    protected $data;

    public function __construct($rentRequest)
    {
        // Instead of storing full model, just store the values you need
        $this->data = [
            'id' => $rentRequest->id,
            'property_id' => $rentRequest->property_id,
            'user_name' => $rentRequest->user->first_name . ' ' . $rentRequest->user->last_name ?? 'Unknown',
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
            'property_id' => $this->data['property_id'],
            'message' => 'New rent request from ' . $this->data['user_name'],
        ];
    }

    public function toBroadcast($notifiable)
    {
        return new BroadcastMessage([
            'id' => $this->data['id'],
            'property_id' => $this->data['property_id'],
            'message' => 'New rent request from ' . $this->data['user_name'],
        ]);
    }
}