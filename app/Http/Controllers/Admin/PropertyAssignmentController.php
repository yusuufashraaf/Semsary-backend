<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AssignCsAgentRequest;
use App\Http\Resources\Admin\CSAgentAssignmentResource;
use App\Models\CSAgentPropertyAssign;
use App\Models\Property;
use App\Models\User;
use App\Models\AuditLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class PropertyAssignmentController extends Controller
{
    /**
     * Assign a property to a CS Agent
     * POST /api/admin/properties/{property}/assign-cs-agent
     */
    public function store(AssignCsAgentRequest $request, Property $property): JsonResponse
    {
        try {
            DB::beginTransaction();

            $validated = $request->validated();

            // Check if property can be assigned
            if (!$property->canBeAssigned()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Property already has an active assignment'
                ], 422);
            }

            // Get CS Agent
            $csAgent = User::find($validated['cs_agent_id']);
            if (!$csAgent || !$csAgent->isActiveCsAgent()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Selected user is not an active CS Agent'
                ], 422);
            }

            // Create assignment
            $assignment = CSAgentPropertyAssign::create([
                'property_id' => $property->id,
                'cs_agent_id' => $validated['cs_agent_id'],
                'assigned_by' => auth()->id(),
                'status' => CSAgentPropertyAssign::STATUS_PENDING,
                'notes' => $validated['notes'] ?? null,
                'assigned_at' => now(),
                'metadata' => [
                    'priority' => $validated['priority'] ?? 'normal',
                    'assigned_by_name' => auth()->user()->getFullNameAttribute(),
                ]
            ]);

            // Load relationships for response
            $assignment->load([
                'property:id,title,type,price,property_state',
                'csAgent:id,first_name,last_name,email',
                'assignedBy:id,first_name,last_name'
            ]);

            // Log admin action
            if (class_exists('App\Models\AuditLog')) {
                AuditLog::log(
                    auth()->id(),
                    'CSAgentAssignment',
                    'assign_property',
                    [
                        'assignment_id' => $assignment->id,
                        'property_id' => $property->id,
                        'cs_agent_id' => $validated['cs_agent_id'],
                        'priority' => $validated['priority'] ?? 'normal'
                    ]
                );
            }

            // TODO: Fire PropertyAssigned event for notifications
            // event(new PropertyAssigned($assignment));

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Property assigned successfully.',
                'data' => [
                    'assignment_id' => $assignment->id,
                    'property_id' => $property->id,
                    'cs_agent_id' => $validated['cs_agent_id'],
                    'status' => $assignment->status,
                    'assigned_at' => $assignment->assigned_at->toISOString()
                ]
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Failed to assign property',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
