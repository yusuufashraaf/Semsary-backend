<?php

namespace App\Notifications;

use App\Models\Property;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PropertyFeedbackNotification extends Notification
{
     use Queueable;

    public $property;
    public $feedback;
    public $status;

    /**
     * Create a new notification instance.
     */
    public function __construct(Property $property, string $status, string $feedback)
    {
        $this->property = $property;
        $this->status = $status;
        $this->feedback = $feedback;
    }


    public function via($notifiable)
    {
        return ['database']; // stores notification in DB, can add 'mail' if needed
    }


    public function toDatabase($notifiable)
    {
        return [
            'property_id' => $this->property->id,
            'property_title' => $this->property->title,
            'status' => $this->status,
            'feedback' => $this->feedback,
            'message' => "Your property '{$this->property->title}' status has been updated to '{$this->status}' with feedback.",
        ];
    }

}
