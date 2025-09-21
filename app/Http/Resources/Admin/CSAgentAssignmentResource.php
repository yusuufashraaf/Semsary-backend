<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CSAgentAssignmentResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'status' => $this->status,
            'formatted_status' => $this->formatted_status,
            'notes' => $this->notes,
            'priority' => $this->priority,
            'assigned_at' => $this->assigned_at?->toISOString(),
            'started_at' => $this->started_at?->toISOString(),
            'completed_at' => $this->completed_at?->toISOString(),
            'duration_hours' => $this->duration,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),

            // Property Information
            'property' => $this->whenLoaded('property', function () {
                return [
                    'id' => $this->property->id,
                    'title' => $this->property->title,
                    'description' => $this->when($this->property->description, $this->property->description),
                    'type' => $this->property->type,
                    'price' => $this->property->price,
                    'formatted_price' => $this->when($this->property->formatted_price, $this->property->formatted_price),
                    'property_state' => $this->property->property_state,
                    'location' => $this->when($this->property->location_string, $this->property->location_string),
                    'created_at' => $this->property->created_at?->toISOString(),

                    // Property Owner
                    'owner' => $this->whenLoaded('property.owner', function () {
                        return [
                            'id' => $this->property->owner->id,
                            'name' => $this->property->owner->full_name,
                            'email' => $this->property->owner->email,
                            'phone_number' => $this->when($this->property->owner->phone_number, $this->property->owner->phone_number),
                        ];
                    }),

                    // Property Images
                    'images' => $this->whenLoaded('property.images', function () {
                        return $this->property->images->map(function ($image) {
                            return [
                                'id' => $image->id,
                                'image_url' => $image->image_url,
                                'is_primary' => $image->is_primary ?? false,
                            ];
                        });
                    }),

                    // Primary Image
                    'primary_image' => $this->when(
                        $this->property->images && $this->property->images->isNotEmpty(),
                        function () {
                            $primaryImage = $this->property->images->where('is_primary', true)->first()
                                         ?? $this->property->images->first();
                            return $primaryImage ? $primaryImage->image_url : null;
                        }
                    ),
                ];
            }),

            // CS Agent Information
            'cs_agent' => $this->whenLoaded('csAgent', function () {
                return [
                    'id' => $this->csAgent->id,
                    'name' => $this->csAgent->full_name,
                    'first_name' => $this->csAgent->first_name,
                    'last_name' => $this->csAgent->last_name,
                    'email' => $this->csAgent->email,
                    'phone_number' => $this->when($this->csAgent->phone_number, $this->csAgent->phone_number),
                    'status' => $this->csAgent->status,

                    // Agent Statistics (if available)
                    'active_assignments' => $this->when(
                        isset($this->csAgent->active_assignments_count),
                        $this->csAgent->active_assignments_count
                    ),
                    'completed_assignments' => $this->when(
                        isset($this->csAgent->completed_assignments_count),
                        $this->csAgent->completed_assignments_count
                    ),
                ];
            }),

            // Admin who assigned
            'assigned_by' => $this->whenLoaded('assignedBy', function () {
                return [
                    'id' => $this->assignedBy->id,
                    'name' => $this->assignedBy->full_name,
                    'email' => $this->assignedBy->email,
                ];
            }),

            // Metadata information
            'metadata' => $this->when($this->metadata, function () {
                $metadata = $this->metadata ?? [];
                return [
                    'priority' => $metadata['priority'] ?? 'normal',
                    'assigned_by_name' => $metadata['assigned_by_name'] ?? null,
                    'bulk_assignment' => $metadata['bulk_assignment'] ?? false,
                    'reassigned' => $metadata['reassigned'] ?? false,
                    'previous_agent_id' => $metadata['previous_agent_id'] ?? null,
                    'reassignment_reason' => $metadata['reassignment_reason'] ?? null,
                    'reassigned_at' => $metadata['reassigned_at'] ?? null,
                    'reassigned_by' => $metadata['reassigned_by'] ?? null,
                ];
            }),

            // Status helpers
            'is_pending' => $this->isPending(),
            'is_in_progress' => $this->isInProgress(),
            'is_completed' => $this->isCompleted(),
            'is_rejected' => $this->isRejected(),

            // Time calculations
            'time_elapsed' => $this->when($this->started_at, function () {
                if ($this->completed_at) {
                    return $this->started_at->diffForHumans($this->completed_at);
                }
                return $this->started_at->diffForHumans();
            }),

            'time_since_assigned' => $this->assigned_at?->diffForHumans(),

            // Urgency indicators
            'is_overdue' => $this->when($this->assigned_at, function () {
                $daysOverdue = now()->diffInDays($this->assigned_at);
                return $this->status === 'pending' && $daysOverdue > 7;
            }),

            'urgency_level' => $this->when($this->assigned_at, function () {
                $priority = $this->metadata['priority'] ?? 'normal';
                $daysElapsed = now()->diffInDays($this->assigned_at);

                if ($priority === 'urgent' && $daysElapsed > 1) {
                    return 'critical';
                } elseif ($priority === 'high' && $daysElapsed > 3) {
                    return 'high';
                } elseif ($daysElapsed > 7 && $this->status === 'pending') {
                    return 'overdue';
                } else {
                    return 'normal';
                }
            }),
        ];
    }

    /**
     * Get additional data that should be wrapped when this resource is returned.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array<string, mixed>
     */
    public function with(Request $request): array
    {
        return [
            'meta' => [
                'available_statuses' => \App\Models\CSAgentPropertyAssign::getStatuses(),
                'priority_levels' => ['low', 'normal', 'high', 'urgent'],
            ],
        ];
    }
}
