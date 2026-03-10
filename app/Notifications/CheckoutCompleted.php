<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\BroadcastMessage;
use App\Models\Checkout;

class CheckoutCompleted extends Notification
{
    use Queueable;

    protected $checkout;

    /**
     * Create a new notification instance.
     */
    public function __construct(Checkout $checkout)
    {
        $this->checkout = $checkout;
    }

    /**
     * Get the notification's delivery channels.
     */
    public function via($notifiable)
    {
        // Store in DB and broadcast over Echo
        return ['database', 'broadcast'];
    }

    /**
     * Get the array representation of the notification for database.
     */
    public function toArray($notifiable)
    {
        return [
            'id'          => $this->checkout->id,
            'message'     => "Checkout #{$this->checkout->id} is {$this->checkout->status}.",
            'property_id' => $this->checkout->rental->property_id ?? null,
            'status'      => $this->checkout->status,
            'type'        => $this->checkout->type,
            'created_at'  => now(),
        ];
    }

    /**
     * Get the broadcastable representation of the notification.
     */
    public function toBroadcast($notifiable)
    {
        return new BroadcastMessage([
            'id'          => $this->checkout->id,
            'message'     => "Checkout #{$this->checkout->id} is {$this->checkout->status}.",
            'property_id' => $this->checkout->rental->property_id ?? null,
            'status'      => $this->checkout->status,
            'type'        => $this->checkout->type,
            'created_at'  => now(),
        ]);
    }
}