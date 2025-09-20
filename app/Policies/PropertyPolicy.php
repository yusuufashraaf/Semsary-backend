<?php

namespace App\Policies;

use App\Models\Property;
use App\Models\User;

class PropertyPolicy
{
    /**
     * Determine if the user can assign a CS agent to the property.
     */
    public function assignCsAgent(User $user, Property $property): bool
    {
        return $user->isAdmin();
    }

    /**
     * Determine if the user can view assigned properties.
     */
    public function viewAssigned(User $user): bool
    {
        return $user->isCsAgent() && $user->isActive();
    }

    /**
     * Determine if the user can update verification status for the property.
     */
    public function updateVerificationStatus(User $user, Property $property): bool
    {
        if (!$user->isCsAgent() || !$user->isActive()) {
            return false;
        }

        // Check if user has an active assignment for this property
        return $property->csAgentAssignments()
            ->where('cs_agent_id', $user->id)
            ->whereIn('status', ['pending', 'in_progress'])
            ->exists();
    }

    /**
     * Determine if the user can upload verification documents for the property.
     */
    public function uploadVerificationDocuments(User $user, Property $property): bool
    {
        return $this->updateVerificationStatus($user, $property);
    }

    /**
     * Determine if the user can view the property details.
     */
    public function view(User $user, Property $property): bool
    {
        // Property owner can view their own property
        if ($user->isOwner() && $property->owner_id === $user->id) {
            return true;
        }

        // Admin can view any property
        if ($user->isAdmin()) {
            return true;
        }

        // CS Agent can view assigned properties
        if ($user->isCsAgent()) {
            return $property->csAgentAssignments()
                ->where('cs_agent_id', $user->id)
                ->exists();
        }

        return false;
    }

    /**
     * Determine if the user can create properties.
     */
    public function create(User $user): bool
    {
        return $user->isOwner() || $user->isAdmin();
    }

    /**
     * Determine if the user can update the property.
     */
    public function update(User $user, Property $property): bool
    {
        // Property owner can update their own property
        if ($user->isOwner() && $property->owner_id === $user->id) {
            return true;
        }

        // Admin can update any property
        return $user->isAdmin();
    }

    /**
     * Determine if the user can delete the property.
     */
    public function delete(User $user, Property $property): bool
    {
        // Only admin can delete properties
        return $user->isAdmin();
    }
}
