<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class IdStatusUpdated extends Notification
{
    use Queueable;

    public string $status;

    public function __construct(string $status)
    {
        $this->status = $status;
    }

    public function via(object $notifiable): array
    {
        return [ 'database']; // + database for notifications
    }


    public function toArray(object $notifiable): array
    {
        return [
            'status' => $this->status,
            'message' => "Your ID document status has been updated to '{$this->status}'.",
        ];
    }
}

