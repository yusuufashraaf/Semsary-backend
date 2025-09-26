<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Messages\BroadcastMessage;

class RentPaidByRenter extends Notification implements ShouldQueue
{
    use Queueable;

    protected $purchase;

    public function __construct($purchase)
    {
        $this->purchase = $purchase;
    }

    /**
     * Channels to deliver the notification.
     */
    public function via($notifiable)
    {
        return ['mail', 'database', 'broadcast'];
    }

    /**
     * Store in database.
     */
    public function toDatabase($notifiable)
    {
        return [
            'purchase_id' => $this->purchase->purchase_id,
            'property_id' => $this->purchase->property_id,
            'amount'      => $this->purchase->amount,
            'status'      => $this->purchase->status,
            'message'     => 'Rent payment successful',
        ];
    }

    /**
     * Broadcast (real-time).
     */
    public function toBroadcast($notifiable)
    {
        return new BroadcastMessage([
            'purchase_id' => $this->purchase->purchase_id,
            'property_id' => $this->purchase->property_id,
            'amount'      => $this->purchase->amount,
            'status'      => $this->purchase->status,
            'message'     => 'Rent payment successful',
        ]);
    }
}