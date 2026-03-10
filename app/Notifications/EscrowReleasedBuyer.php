<?php

namespace App\Notifications;

use App\Models\PropertyEscrow;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class EscrowReleasedBuyer extends Notification
{
    use Queueable;

    protected $escrow;

    public function __construct(PropertyEscrow $escrow)
    {
        $this->escrow = $escrow;
    }

    public function via($notifiable)
    {
        return ['database', 'broadcast']; // add 'mail' if you want emails
    }

    public function toDatabase($notifiable)
    {
        return [
            'escrow_id'   => $this->escrow->id,
            'property_id' => $this->escrow->property_id,
            'amount'      => $this->escrow->amount,
            'message'     => "Your escrow of {$this->escrow->amount} has been released.",
        ];
    }

    public function toBroadcast($notifiable)
    {
        return [
            'escrow_id'   => $this->escrow->id,
            'property_id' => $this->escrow->property_id,
            'amount'      => $this->escrow->amount,
            'message'     => "Your escrow of {$this->escrow->amount} has been released.",
        ];
    }
}