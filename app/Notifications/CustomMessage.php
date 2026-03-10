<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class CustomMessage extends Notification implements ShouldQueue
{
    use Queueable;

    protected $data;

    public function __construct($message,$id)
    {
        // Instead of storing full model, just store the values you need
        $this->data = [
            'id' => $id,
            'message' => $message
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
            'message' => 'Message from Admin to User-' . $this->data['id'] . " : " .$this->data['message'] ,
        ];
    }

    public function toBroadcast($notifiable)
    {
        return new BroadcastMessage([
            'id' => $this->data['id'],
            'message' => $this->data['message'] ,
        ]);
    }
}
