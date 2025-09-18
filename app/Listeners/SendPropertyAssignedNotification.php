<?php

namespace App\Listeners;

use App\Events\PropertyAssigned;
use App\Models\Notification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class SendPropertyAssignedNotification implements ShouldQueue
{
    use InteractsWithQueue;

    /**
     * Handle the event.
     */
    public function handle(PropertyAssigned $event): void
    {
        $assignment = $event->assignment;

        // Create a notification for the CS Agent
        Notification::create([
            'user_id' => $assignment->cs_agent_id,
            'title' => 'New Property Assignment',
            'message' => "You have been assigned to verify property: {$assignment->property->title}",
            'type' => 'property_assignment',
            'data' => json_encode([
                'assignment_id' => $assignment->id,
                'property_id' => $assignment->property_id,
                'property_title' => $assignment->property->title,
                'assigned_by' => $assignment->assignedBy->getFullNameAttribute(),
                'priority' => $assignment->metadata['priority'] ?? 'normal'
            ]),
            'read_at' => null
        ]);

        // TODO: Send email notification
        // TODO: Send push notification
    }
}
