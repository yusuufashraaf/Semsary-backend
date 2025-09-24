<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\DatabaseMessage;

class InsufficientWalletBalance extends Notification implements ShouldQueue
{
    use Queueable;

    protected float $required;
    protected float $current;

    /**
     * Create a new notification instance.
     */
    public function __construct(float $required, float $current)
    {
        $this->required = $required;
        $this->current  = $current;
    }

    /**
     * Get the notification's delivery channels.
     */
    public function via(object $notifiable): array
    {
        return ['database']; // add 'broadcast' or 'mail' if you want
    }

    /**
     * Store notification in database.
     */
    public function toDatabase(object $notifiable): array
    {
        return [
            'title'    => 'Insufficient Wallet Balance',
            'message'  => "You need {$this->required} EGP but only {$this->current} EGP is available in your wallet.",
            'required' => $this->required,
            'current'  => $this->current,
            'shortfall'=> $this->required - $this->current,
        ];
    }

    /**
     * (Optional) Broadcast notification for real-time.
     */
    public function toBroadcast(object $notifiable): DatabaseMessage
    {
        return new DatabaseMessage($this->toDatabase($notifiable));
    }
}