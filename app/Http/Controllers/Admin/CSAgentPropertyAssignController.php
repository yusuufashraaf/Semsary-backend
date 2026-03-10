<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CreateAssignmentRequest;
use App\Http\Requests\Admin\UpdateAssignmentRequest;
use App\Http\Requests\Admin\AssignmentFilterRequest;
use App\Http\Resources\Admin\CSAgentAssignmentResource;
use App\Models\CSAgentPropertyAssign;
use App\Models\User;
use App\Models\Property;
use App\Models\AuditLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CSAgentPropertyAssignController extends Controller
{
    /**
     * Get paginated list of CS agent assignments with filtering
     */
    public function index(AssignmentFilterRequest $request): JsonResponse
    {
        try {
            $filters = $request->validated();

            $assignments = CSAgentPropertyAssign::with([
                'property:id,title,type,price,property_state,owner_id',
                'property.owner:id,first_name,last_name,email',
                'csAgent:id,first_name,last_name,email',
                'assignedBy:id,first_name,last_name'
            ])
            ->withFilters($filters)
            ->orderBy($filters['sort_by'], $filters['sort_order'])
            ->paginate($filters['per_page']);

            // Log admin action
            AuditLog::log(
                auth()->id(),
                'CSAgentAssignment',
                'view_assignments',
                ['filters' => $filters, 'count' => $assignments->total()]
            );

            return response()->json([
                'status' => 'success',
                'data' => CSAgentAssignmentResource::collection($assignments->items()),
                'pagination' => [
                    'current_page' => $assignments->currentPage(),
                    'last_page' => $assignments->lastPage(),
                    'per_page' => $assignments->perPage(),
                    'total' => $assignments->total(),
                    'from' => $assignments->firstItem(),
                    'to' => $assignments->lastItem(),
                ],
                'filters_applied' => array_filter($filters, fn($value) => !is_null($value) && $value !== '')
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve assignments',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Create a new CS agent assignment
     */
    public function store(CreateAssignmentRequest $request): JsonResponse
    {
        try {
            DB::beginTransaction();

            $validated = $request->validated();

            // Create assignment
            $assignment = CSAgentPropertyAssign::create([
                'property_id' => $validated['property_id'],
                'cs_agent_id' => $validated['cs_agent_id'],
                'assigned_by' => auth()->id(),
                'status' => CSAgentPropertyAssign::STATUS_PENDING,
                'notes' => $validated['notes'] ?? null,
                'assigned_at' => $validated['assigned_at'],
                'metadata' => [
                    'priority' => $validated['priority'] ?? 'normal',
                    'assigned_by_name' => auth()->user()->full_name,
                ]
            ]);

            // Load relationships for response
            $assignment->load([
                'property:id,title,type,price,property_state',
                'csAgent:id,first_name,last_name,email',
                'assignedBy:id,first_name,last_name'
            ]);

            // Log admin action
            AuditLog::log(
                auth()->id(),
                'CSAgentAssignment',
                'create_assignment',
                [
                    'assignment_id' => $assignment->id,
                    'property_id' => $assignment->property_id,
                    'cs_agent_id' => $assignment->cs_agent_id,
                    'priority' => $validated['priority'] ?? 'normal'
                ]
            );

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Property assigned to CS agent successfully',
                'data' => new CSAgentAssignmentResource($assignment)
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to create assignment',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified assignment
     */
    public function show(int $id): JsonResponse
    {
        try {
            $assignment = CSAgentPropertyAssign::with([
                'property:id,title,description,type,price,property_state,owner_id,created_at',
                'property.owner:id,first_name,last_name,email,phone_number',
                'property.images:id,property_id,image_url,is_primary',
                'csAgent:id,first_name,last_name,email,phone_number',
                'assignedBy:id,first_name,last_name,email'
            ])->findOrFail($id);

            // Log admin action
            AuditLog::log(
                auth()->id(),
                'CSAgentAssignment',
                'view_assignment',
                ['assignment_id' => $assignment->id]
            );

            return response()->json([
                'status' => 'success',
                'data' => new CSAgentAssignmentResource($assignment)
            ]);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Assignment not found'
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve assignment',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update the specified assignment
     */
    public function update(UpdateAssignmentRequest $request, int $id): JsonResponse
    {
        try {
            DB::beginTransaction();

            $assignment = CSAgentPropertyAssign::findOrFail($id);
            $validated = $request->validated();

            $oldStatus = $assignment->status;
            $newStatus = $validated['status'];

            // Update assignment based on status change
            $updateData = [
                'status' => $newStatus,
                'notes' => $validated['notes'] ?? $assignment->notes,
            ];

            // Handle metadata update
            $metadata = $assignment->metadata ?? [];
            if (isset($validated['priority'])) {
                $metadata['priority'] = $validated['priority'];
            }
            $updateData['metadata'] = $metadata;

            // Set timestamps based on status transitions
            switch ($newStatus) {
                case CSAgentPropertyAssign::STATUS_IN_PROGRESS:
                    if ($oldStatus === CSAgentPropertyAssign::STATUS_PENDING) {
                        $updateData['started_at'] = now();
                    }
                    break;

                case CSAgentPropertyAssign::STATUS_COMPLETED:
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
                'csAgent:id,first_name,last_name,email',
                'assignedBy:id,first_name,last_name'
            ]);

            // Log admin action
            AuditLog::log(
                auth()->id(),
                'CSAgentAssignment',
                'update_assignment',
                [
                    'assignment_id' => $assignment->id,
                    'old_status' => $oldStatus,
                    'new_status' => $newStatus,
                    'notes' => $validated['notes'] ?? null
                ]
            );

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => "Assignment status updated from '{$oldStatus}' to '{$newStatus}' successfully",
                'data' => new CSAgentAssignmentResource($assignment)
            ]);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            DB::rollBack();
            return response()->json([
                'status' => 'error',
                'message' => 'Assignment not found'
            ], 404);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to update assignment',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove the specified assignment
     */
    public function destroy(int $id): JsonResponse
    {
        try {
            DB::beginTransaction();

            $assignment = CSAgentPropertyAssign::findOrFail($id);

            // Only allow deletion of pending assignments
            if (!$assignment->isPending()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Only pending assignments can be deleted'
                ], 422);
            }

            // Log admin action before deletion
            AuditLog::log(
                auth()->id(),
                'CSAgentAssignment',
                'delete_assignment',
                [
                    'assignment_id' => $assignment->id,
                    'property_id' => $assignment->property_id,
                    'cs_agent_id' => $assignment->cs_agent_id,
                    'status' => $assignment->status
                ]
            );

            $assignment->delete();

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Assignment deleted successfully'
            ]);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            DB::rollBack();
            return response()->json([
                'status' => 'error',
                'message' => 'Assignment not found'
            ], 404);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to delete assignment',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get available CS agents for assignment
     */
    public function getAvailableAgents(Request $request): JsonResponse
    {
        try {
            $agents = User::csAgents()
                ->select('id', 'first_name', 'last_name', 'email')
                ->withCount([
                    'csAgentAssignments as active_assignments_count' => function ($query) {
                        $query->whereIn('status', ['pending', 'in_progress']);
                    },
                    'csAgentAssignments as completed_assignments_count' => function ($query) {
                        $query->where('status', 'completed');
                    }
                ])
                ->get()
                ->map(function ($agent) {
                    return [
                        'id' => $agent->id,
                        'name' => $agent->full_name,
                        'email' => $agent->email,
                        'active_assignments' => $agent->active_assignments_count,
                        'completed_assignments' => $agent->completed_assignments_count,
                        'workload_status' => $agent->active_assignments_count >= 10 ? 'high' :
                                          ($agent->active_assignments_count >= 5 ? 'medium' : 'low')
                    ];
                });

            return response()->json([
                'status' => 'success',
                'data' => $agents
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve available agents',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get assignment statistics
     */
    public function getStatistics(Request $request): JsonResponse
    {
        try {
            $dateFrom = $request->input('date_from', now()->subMonth());
            $dateTo = $request->input('date_to', now());

            $stats = [
                'total_assignments' => CSAgentPropertyAssign::whereBetween('assigned_at', [$dateFrom, $dateTo])->count(),
                'pending_assignments' => CSAgentPropertyAssign::pending()->whereBetween('assigned_at', [$dateFrom, $dateTo])->count(),
                'in_progress_assignments' => CSAgentPropertyAssign::inProgress()->whereBetween('assigned_at', [$dateFrom, $dateTo])->count(),
                'completed_assignments' => CSAgentPropertyAssign::completed()->whereBetween('assigned_at', [$dateFrom, $dateTo])->count(),
                'rejected_assignments' => CSAgentPropertyAssign::rejected()->whereBetween('assigned_at', [$dateFrom, $dateTo])->count(),
                'average_completion_time' => $this->getAverageCompletionTime($dateFrom, $dateTo),
                'top_performing_agents' => $this->getTopPerformingAgents($dateFrom, $dateTo),
                'assignments_by_status' => $this->getAssignmentsByStatus($dateFrom, $dateTo),
            ];

            return response()->json([
                'status' => 'success',
                'data' => $stats,
                'period' => [
                    'from' => $dateFrom,
                    'to' => $dateTo
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve statistics',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get average completion time for assignments
     */
    private function getAverageCompletionTime(string $dateFrom, string $dateTo): ?float
    {
        $completedAssignments = CSAgentPropertyAssign::completed()
            ->whereBetween('assigned_at', [$dateFrom, $dateTo])
            ->whereNotNull('started_at')
            ->whereNotNull('completed_at')
            ->get();

        if ($completedAssignments->isEmpty()) {
            return null;
        }

        $totalHours = $completedAssignments->sum(function ($assignment) {
            return $assignment->started_at->diffInHours($assignment->completed_at);
        });

        return round($totalHours / $completedAssignments->count(), 2);
    }

    /**
     * Get top performing agents
     */
    private function getTopPerformingAgents(string $dateFrom, string $dateTo, int $limit = 5): array
    {
        return User::csAgents()
            ->withCount([
                'csAgentAssignments as completed_count' => function ($query) use ($dateFrom, $dateTo) {
                    $query->where('status', 'completed')
                          ->whereBetween('assigned_at', [$dateFrom, $dateTo]);
                },
                'csAgentAssignments as total_count' => function ($query) use ($dateFrom, $dateTo) {
                    $query->whereBetween('assigned_at', [$dateFrom, $dateTo]);
                }
            ])
            ->having('completed_count', '>', 0)
            ->orderByDesc('completed_count')
            ->limit($limit)
            ->get()
            ->map(function ($agent) {
                $completionRate = $agent->total_count > 0
                    ? round(($agent->completed_count / $agent->total_count) * 100, 2)
                    : 0;

                return [
                    'id' => $agent->id,
                    'name' => $agent->full_name,
                    'email' => $agent->email,
                    'completed_assignments' => $agent->completed_count,
                    'total_assignments' => $agent->total_count,
                    'completion_rate' => $completionRate,
                    'average_completion_time' => $agent->getAverageCompletionTime()
                ];
            })
            ->toArray();
    }

    /**
     * Get assignments breakdown by status
     */
    private function getAssignmentsByStatus(string $dateFrom, string $dateTo): array
    {
        $statusCounts = CSAgentPropertyAssign::selectRaw('status, COUNT(*) as count')
            ->whereBetween('assigned_at', [$dateFrom, $dateTo])
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();

        $statuses = CSAgentPropertyAssign::getStatuses();
        $result = [];

        foreach ($statuses as $status) {
            $result[] = [
                'status' => $status,
                'count' => $statusCounts[$status] ?? 0,
                'label' => ucfirst(str_replace('_', ' ', $status))
            ];
        }

        return $result;
    }

    /**
     * Bulk assign properties to CS agents
     */
    public function bulkAssign(Request $request): JsonResponse
    {
        $request->validate([
            'property_ids' => 'required|array|min:1',
            'property_ids.*' => 'integer|exists:properties,id',
            'cs_agent_id' => 'required|integer|exists:users,id',
            'notes' => 'nullable|string|max:1000',
            'priority' => 'nullable|in:low,normal,high,urgent'
        ]);

        try {
            DB::beginTransaction();

            $propertyIds = $request->input('property_ids');
            $csAgentId = $request->input('cs_agent_id');
            $notes = $request->input('notes');
            $priority = $request->input('priority', 'normal');

            // Check if CS Agent is valid
            $csAgent = User::find($csAgentId);
            if (!$csAgent || $csAgent->role !== 'agent' || $csAgent->status !== 'active') {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Selected user is not an active CS Agent'
                ], 422);
            }

            // Check which properties can be assigned
            $properties = Property::whereIn('id', $propertyIds)->get();
            $assignableProperties = [];
            $nonAssignableProperties = [];

            foreach ($properties as $property) {
                if ($property->canBeAssigned()) {
                    $assignableProperties[] = $property;
                } else {
                    $nonAssignableProperties[] = [
                        'id' => $property->id,
                        'title' => $property->title,
                        'reason' => 'Already has active assignment'
                    ];
                }
            }

            if (empty($assignableProperties)) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'No properties can be assigned',
                    'non_assignable_properties' => $nonAssignableProperties
                ], 422);
            }

            // Create bulk assignments
            $assignments = [];
            $assignedAt = now();

            foreach ($assignableProperties as $property) {
                $assignments[] = [
                    'property_id' => $property->id,
                    'cs_agent_id' => $csAgentId,
                    'assigned_by' => auth()->id(),
                    'status' => CSAgentPropertyAssign::STATUS_PENDING,
                    'notes' => $notes,
                    'assigned_at' => $assignedAt,
                    'metadata' => json_encode([
                        'priority' => $priority,
                        'assigned_by_name' => auth()->user()->full_name,
                        'bulk_assignment' => true
                    ]),
                    'created_at' => now(),
                    'updated_at' => now()
                ];
            }

            CSAgentPropertyAssign::insert($assignments);

            // Log admin action
            AuditLog::log(
                auth()->id(),
                'CSAgentAssignment',
                'bulk_assign',
                [
                    'cs_agent_id' => $csAgentId,
                    'assigned_properties' => array_column($assignments, 'property_id'),
                    'count' => count($assignments),
                    'priority' => $priority
                ]
            );

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => count($assignments) . ' properties assigned successfully to CS agent',
                'data' => [
                    'assigned_count' => count($assignments),
                    'assigned_properties' => array_map(fn($p) => [
                        'id' => $p->id,
                        'title' => $p->title
                    ], $assignableProperties),
                    'non_assignable_properties' => $nonAssignableProperties,
                    'cs_agent' => [
                        'id' => $csAgent->id,
                        'name' => $csAgent->full_name,
                        'email' => $csAgent->email
                    ]
                ]
            ]);

        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to bulk assign properties',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Reassign an assignment to different CS agent
     */
    public function reassign(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'new_cs_agent_id' => 'required|integer|exists:users,id',
            'reason' => 'required|string|max:500'
        ]);

        try {
            DB::beginTransaction();

            $assignment = CSAgentPropertyAssign::findOrFail($id);

            // Can only reassign pending or in-progress assignments
            if (!in_array($assignment->status, ['pending', 'in_progress'])) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Can only reassign pending or in-progress assignments'
                ], 422);
            }

            $newCSAgentId = $request->input('new_cs_agent_id');
            $reason = $request->input('reason');

            // Check if new CS Agent is valid
            $newCSAgent = User::find($newCSAgentId);
            if (!$newCSAgent || $newCSAgent->role !== 'agent' || $newCSAgent->status !== 'active') {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Selected user is not an active CS Agent'
                ], 422);
            }

            $oldCSAgentId = $assignment->cs_agent_id;

            // Update assignment
            $assignment->update([
                'cs_agent_id' => $newCSAgentId,
                'status' => CSAgentPropertyAssign::STATUS_PENDING, // Reset to pending
                'notes' => $assignment->notes . "\n\n[REASSIGNED] " . $reason,
                'assigned_at' => now(),
                'started_at' => null, // Reset start time
                'completed_at' => null, // Reset completion time
                'metadata' => array_merge($assignment->metadata ?? [], [
                    'reassigned' => true,
                    'previous_agent_id' => $oldCSAgentId,
                    'reassignment_reason' => $reason,
                    'reassigned_at' => now()->toISOString(),
                    'reassigned_by' => auth()->user()->full_name
                ])
            ]);

            // Load relationships for response
            $assignment->load([
                'property:id,title,type,price,property_state',
                'csAgent:id,first_name,last_name,email',
                'assignedBy:id,first_name,last_name'
            ]);

            // Log admin action
            AuditLog::log(
                auth()->id(),
                'CSAgentAssignment',
                'reassign',
                [
                    'assignment_id' => $assignment->id,
                    'old_cs_agent_id' => $oldCSAgentId,
                    'new_cs_agent_id' => $newCSAgentId,
                    'reason' => $reason
                ]
            );

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Assignment reassigned successfully',
                'data' => new CSAgentAssignmentResource($assignment)
            ]);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            DB::rollBack();
            return response()->json([
                'status' => 'error',
                'message' => 'Assignment not found'
            ], 404);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to reassign assignment',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
