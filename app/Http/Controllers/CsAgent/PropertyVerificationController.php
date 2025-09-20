<?php

namespace App\Http\Controllers\CsAgent;

use App\Http\Controllers\Controller;
use App\Http\Requests\CsAgent\UpdateVerificationStatusRequest;
use App\Http\Resources\Admin\CSAgentAssignmentResource;
use App\Models\CSAgentPropertyAssign;
use App\Models\Property;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class PropertyVerificationController extends Controller
{
    /**
     * Update verification status of a property
     * PATCH /api/cs-agent/properties/{property}/status
     */
    public function update(UpdateVerificationStatusRequest $request, Property $property): JsonResponse
    {
        try {
            DB::beginTransaction();

            $user = auth()->user();
            $validated = $request->validated();

            // Find the assignment for this property and current CS agent
            $assignment = CSAgentPropertyAssign::where('property_id', $property->id)
                ->where('cs_agent_id', $user->id)
                ->whereIn('status', ['pending', 'in_progress'])
                ->first();

            if (!$assignment) {
                return response()->json([
                    'success' => false,
                    'message' => 'No active assignment found for this property'
                ], 404);
            }

            $oldStatus = $assignment->status;
            $newStatus = $validated['status'];

            // Update assignment
            $updateData = [
                'status' => $newStatus,
                'notes' => $validated['notes'] ?? $assignment->notes,
            ];

            // Set timestamps based on status transitions
            switch ($newStatus) {
                case CSAgentPropertyAssign::STATUS_IN_PROGRESS:
                    if ($oldStatus === CSAgentPropertyAssign::STATUS_PENDING) {
                        $updateData['started_at'] = now();
                    }
                    break;

                case CSAgentPropertyAssign::STATUS_COMPLETED:
                    if (!$assignment->completed_at) {
                        $updateData['completed_at'] = now();
                    }
                    break;

                case CSAgentPropertyAssign::STATUS_REJECTED:
                    if (!$assignment->completed_at) {
                        $updateData['completed_at'] = now();
                    }
                    break;
            }

            $assignment->update($updateData);

            // Load relationships for response
            $assignment->load([
                'property:id,title,type,price,property_state',
                'property.owner:id,first_name,last_name,email',
                'csAgent:id,first_name,last_name,email',
                'assignedBy:id,first_name,last_name'
            ]);

            // Log the action (if AuditLog exists)
            if (class_exists('App\Models\AuditLog')) {
                \App\Models\AuditLog::log(
                    $user->id,
                    'CSAgentAssignment',
                    'update_status',
                    [
                        'assignment_id' => $assignment->id,
                        'property_id' => $property->id,
                        'old_status' => $oldStatus,
                        'new_status' => $newStatus,
                        'notes' => $validated['notes'] ?? null
                    ]
                );
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => "Verification status updated from '{$oldStatus}' to '{$newStatus}' successfully",
                'data' => new CSAgentAssignmentResource($assignment)
            ]);

        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Failed to update verification status',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
