<?php
namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class UserRejectsRequest extends Notification implements ShouldQueue
{
    use Queueable;

    protected $rentRequest;

    public function __construct($rentRequest)
    {
        $this->rentRequest = $rentRequest;
    }

    public function via($notifiable)
    {
        return ['database', 'broadcast'];
    }

    public function toDatabase($notifiable)
    {
        return [
            'rent_request_id' => $this->rentRequest->id,
            'message' => 'The user has rejected your rent request.',
        ];
    }

    public function toBroadcast($notifiable)
    {
        return new BroadcastMessage([
            'rent_request_id' => $this->rentRequest->id,
            'message' => 'The user has rejected your rent request.',
        ]);
    }
}