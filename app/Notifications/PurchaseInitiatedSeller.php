<?php

namespace App\Notifications;

use App\Models\PropertyPurchase;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\DatabaseMessage;
use Illuminate\Notifications\Messages\BroadcastMessage;

class PurchaseInitiatedSeller extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        protected PropertyPurchase $purchase
    ) {}

    public function via($notifiable)
    {
        return ['database', 'broadcast'];
    }

    public function toDatabase($notifiable)
    {
        return [
            'purchase_id' => $this->purchase->id,
            'property_id' => $this->purchase->property_id,
            'message'     => 'A buyer has initiated a purchase for your property.',
        ];
    }

    public function toBroadcast($notifiable)
    {
        return new BroadcastMessage($this->toDatabase($notifiable));
    }
}