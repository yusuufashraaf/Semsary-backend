<?php

use App\Models\Property;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\BroadcastMessage;

class PropertyFeedbackNotification extends Notification
{
    use Queueable;

    public $property;
    public $feedback;
    public $status;

    public function __construct(Property $property, string $status, string $feedback)
    {
        $this->property = $property;
        $this->status   = $status;
        $this->feedback = $feedback;
    }

    public function via($notifiable)
    {
        // Save in DB + send via broadcast channel
        return ['database', 'broadcast'];
    }

    public function toDatabase($notifiable)
    {
        return [
            'property_id'    => $this->property->id,
            'property_title' => $this->property->title,
            'status'         => $this->status,
            'feedback'       => $this->feedback,
            'message'        => "Your property '{$this->property->title}' status has been updated to '{$this->status}' with feedback.",
        ];
    }

   public function toBroadcast($notifiable)
    {
        return new BroadcastMessage([
            'id'              => $this->id, // matches notifications table
            'type'            => static::class,
            'notifiable_id'   => $notifiable->id,
            'notifiable_type' => get_class($notifiable),
            'data' => [
                'property_id'    => $this->property->id,
                'property_title' => $this->property->title,
                'status'         => $this->status,
                'feedback'       => $this->feedback,
                'message'        => "Your property '{$this->property->title}' status has been updated to '{$this->status}' with feedback.",
                'created_at'     => now()->toDateTimeString(),
            ],
        ]);
    }

}
