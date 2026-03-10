<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;

class UserActivated extends Notification implements ShouldQueue
{
    use Queueable;

    protected $data;

    public function __construct($user)
    {
        // Instead of storing full model, just store the values you need
        $this->data = [
            'id' => $user->id,
            'user_id' => $user->id,
            'user_name' => $user->first_name . ' ' . $user->last_name ?? 'Unknown',
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
            'user_id' => $this->data['user_id'],
            'message' => 'User activated: ' . $this->data['user_name'],
        ];
    }

    public function toBroadcast($notifiable)
    {
        return new BroadcastMessage([
            'id' => $this->data['id'],
            'user_id' => $this->data['user_id'],
            'message' => 'User activated: ' . $this->data['user_name'],
        ]);
    }
}