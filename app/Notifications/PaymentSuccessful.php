<?php
namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PaymentSuccessful extends Notification implements ShouldQueue
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
            'message' => 'Payment successfully processed for your booking.',
        ];
    }

    public function toBroadcast($notifiable)
    {
        return new BroadcastMessage([
            'rent_request_id' => $this->rentRequest->id,
            'message' => 'Payment successfully processed for your booking.',
        ]);
    }
}