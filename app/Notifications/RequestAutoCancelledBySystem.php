<?php
namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Notification to owner when a request is auto-cancelled due to payment deadline
 */
class RequestAutoCancelledBySystem extends Notification implements ShouldQueue
{
    use Queueable;

    protected $rentRequest;

    public function __construct($rentRequest)
    {
        $this->rentRequest = $rentRequest;
    }

    public function via($notifiable)
    {
        return ['database', 'broadcast', 'mail'];
    }

    public function toDatabase($notifiable)
    {
        return [
            'rent_request_id' => $this->rentRequest->id,
            'property_id' => $this->rentRequest->property_id,
            'property_title' => $this->rentRequest->property->title ?? 'Property',
            'renter_name' => $this->rentRequest->user->name ?? 'Renter',
            'message' => 'A rent request for your property was automatically cancelled due to payment deadline expiry.',
            'type' => 'request_auto_cancelled_system',
            'action_required' => false,
        ];
    }

    public function toBroadcast($notifiable)
    {
        return new BroadcastMessage([
            'rent_request_id' => $this->rentRequest->id,
            'property_id' => $this->rentRequest->property_id,
            'property_title' => $this->rentRequest->property->title ?? 'Property',
            'renter_name' => $this->rentRequest->user->name ?? 'Renter',
            'message' => 'A rent request for your property was automatically cancelled due to payment deadline expiry.',
            'type' => 'request_auto_cancelled_system',
            'timestamp' => now()->toISOString(),
        ]);
    }

}