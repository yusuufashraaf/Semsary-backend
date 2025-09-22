<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;

class PropertyPurchaseInitiated extends Notification implements ShouldQueue
{
    use Queueable;

    protected $purchase;

    public function __construct($purchase)
    {
        $this->purchase = $purchase;
    }

    public function via($notifiable)
    {
        return ['database', 'broadcast'];
    }

    public function toDatabase($notifiable)
    {
        return [
            'purchase_id' => $this->purchase->id,
            'property_id' => $this->purchase->property_id,
            'message' => 'Your purchase for property ' . $this->purchase->property->name . ' has been initiated.',
        ];
    }

    public function toBroadcast($notifiable)
    {
        return new BroadcastMessage([
            'purchase_id' => $this->purchase->id,
            'property_id' => $this->purchase->property_id,
            'message' => 'Your purchase for property ' . $this->purchase->property->name . ' has been initiated.',
        ]);
    }
}