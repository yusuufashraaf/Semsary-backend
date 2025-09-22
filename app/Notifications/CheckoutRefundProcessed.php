<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;

class CheckoutRefundProcessed extends Notification implements ShouldQueue
{
    use Queueable;

    protected $checkout;

    public function __construct($checkout)
    {
        $this->checkout = $checkout;
    }

    public function via($notifiable)
    {
        return ['database', 'broadcast'];
    }

    public function toDatabase($notifiable)
    {
        return [
            'checkout_id' => $this->checkout->id,
            'rent_request_id' => $this->checkout->rent_request_id,
            'property_id' => $this->checkout->rentRequest->property_id,
            'amount' => $this->checkout->final_refund_amount,
            'message' => 'Your refund of ' . $this->checkout->final_refund_amount 
                . ' has been processed for property ' 
                . $this->checkout->rentRequest->property->title,
        ];
    }

    public function toBroadcast($notifiable)
    {
        return new BroadcastMessage($this->toDatabase($notifiable));
    }
}