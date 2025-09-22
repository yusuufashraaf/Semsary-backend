<?php

namespace App\Notifications;

use App\Models\PropertyPurchase;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class PurchaseCancelled extends Notification
{
    use Queueable;

    protected $purchase;

    public function __construct(PropertyPurchase $purchase)
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
            'message'     => 'Your purchase has been cancelled and money refunded.',
        ];
    }

    public function toBroadcast($notifiable)
    {
        return [
            'data' => [
                'purchase_id' => $this->purchase->id,
                'property_id' => $this->purchase->property_id,
                'message'     => 'Your purchase has been cancelled and money refunded.',
            ]
        ];
    }
}