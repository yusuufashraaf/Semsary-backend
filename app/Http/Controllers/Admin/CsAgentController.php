<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Http\Resources\Admin\CSAgentAssignmentResource;
use App\Models\User;
use App\Models\CSAgentPropertyAssign;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CsAgentController extends Controller
{
    /**
     * List CS Agents
     * GET /api/admin/cs-agents
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $perPage = $request->input('per_page', 15);
            $search = $request->input('search');
            $status = $request->input('status');

            $query = User::csAgents()
                ->select('id', 'first_name', 'last_name', 'email', 'status', 'created_at')
                ->withCount([
                    'csAgentAssignments as active_assignments' => function ($q) {
                        $q->whereIn('status', ['pending', 'in_progress']);
                    },
                    'csAgentAssignments as completed_assignments' => function ($q) {
                        $q->where('status', 'completed');
                    }
                ]);

            // Apply filters
            if ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('first_name', 'like', "%{$search}%")
                      ->orWhere('last_name', 'like', "%{$search}%")
                      ->orWhere('email', 'like', "%{$search}%");
                });
            }

            if ($status) {
                $query->where('status', $status);
            }

            $csAgents = $query->orderBy('first_name')
                ->paginate($perPage);

            // Transform the data
            $transformedData = $csAgents->getCollection()->map(function ($agent) {
                return [
                    'id' => $agent->id,
                    'name' => $agent->getFullNameAttribute(),
                    'first_name' => $agent->first_name,
                    'last_name' => $agent->last_name,
                    'email' => $agent->email,
                    'status' => $agent->status,
                    'active_assignments' => $agent->active_assignments,
                    'completed_assignments' => $agent->completed_assignments,
                    'workload_status' => $agent->active_assignments >= 10 ? 'high' :
                                      ($agent->active_assignments >= 5 ? 'medium' : 'low'),
                    'created_at' => $agent->created_at->toISOString()
                ];
            });

            return response()->json([
                'success' => true,
                'data' => $transformedData,
                'links' => [
                    'first' => $csAgents->url(1),
                    'last' => $csAgents->url($csAgents->lastPage()),
                    'prev' => $csAgents->previousPageUrl(),
                    'next' => $csAgents->nextPageUrl(),
                ],
                'meta' => [
                    'current_page' => $csAgents->currentPage(),
                    'from' => $csAgents->firstItem(),
                    'last_page' => $csAgents->lastPage(),
                    'per_page' => $csAgents->perPage(),
                    'to' => $csAgents->lastItem(),
                    'total' => $csAgents->total(),
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve CS agents',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get detailed information about a specific CS Agent
     * GET /api/admin/cs-agents/{id}
     */
    public function show(Request $request, int $id): JsonResponse
    {
        try {
            $csAgent = User::csAgents()
                ->select('id', 'first_name', 'last_name', 'email', 'status', 'phone_number', 'created_at', 'updated_at')
                ->withCount([
                    'csAgentAssignments as active_assignments' => function ($q) {
                        $q->whereIn('status', ['pending', 'in_progress']);
                    },
                    'csAgentAssignments as completed_assignments' => function ($q) {
                        $q->where('status', 'completed');
                    },
                    'csAgentAssignments as rejected_assignments' => function ($q) {
                        $q->where('status', 'rejected');
                    },
                    'csAgentAssignments as total_assignments' => function ($q) {
                        // Count all assignments
                    }
                ])
                ->find($id);

            if (!$csAgent) {
                return response()->json([
                    'success' => false,
                    'message' => 'CS Agent not found'
                ], 404);
            }

            // Calculate additional metrics
            $totalAssignments = $csAgent->total_assignments;
            $completedAssignments = $csAgent->completed_assignments;
            $successRate = $totalAssignments > 0 ? round(($completedAssignments / $totalAssignments) * 100, 2) : 0;

            // Calculate average completion time
            $avgCompletionTime = CSAgentPropertyAssign::where('cs_agent_id', $id)
                ->where('status', 'completed')
                ->whereNotNull('completed_at')
                ->whereNotNull('started_at')
                ->selectRaw('AVG(TIMESTAMPDIFF(HOUR, started_at, completed_at)) as avg_hours')
                ->value('avg_hours');

            $avgCompletionTime = $avgCompletionTime ? round($avgCompletionTime, 1) : null;

            // Get recent activity (last 10 assignments)
            $recentAssignments = CSAgentPropertyAssign::where('cs_agent_id', $id)
                ->with(['property:id,title,type,property_state'])
                ->orderBy('assigned_at', 'desc')
                ->limit(5)
                ->get();

            $transformedAgent = [
                'id' => $csAgent->id,
                'name' => $csAgent->getFullNameAttribute(),
                'first_name' => $csAgent->first_name,
                'last_name' => $csAgent->last_name,
                'email' => $csAgent->email,
                'phone_number' => $csAgent->phone_number,
                'status' => $csAgent->status,
                'created_at' => $csAgent->created_at->toISOString(),
                'updated_at' => $csAgent->updated_at->toISOString(),
                
                // Assignment statistics
                'active_assignments' => $csAgent->active_assignments,
                'completed_assignments' => $csAgent->completed_assignments,
                'rejected_assignments' => $csAgent->rejected_assignments,
                'total_assignments' => $csAgent->total_assignments,
                
                // Performance metrics
                'success_rate' => $successRate,
                'average_completion_time_hours' => $avgCompletionTime,
                'workload_status' => $csAgent->active_assignments >= 10 ? 'high' :
                                  ($csAgent->active_assignments >= 5 ? 'medium' : 'low'),
                
                // Recent activity
                'recent_assignments' => $recentAssignments->map(function ($assignment) {
                    return [
                        'id' => $assignment->id,
                        'property_id' => $assignment->property_id,
                        'property_title' => $assignment->property->title ?? 'Unknown',
                        'property_type' => $assignment->property->type ?? 'Unknown',
                        'property_status' => $assignment->property->property_state ?? 'Unknown',
                        'assignment_status' => $assignment->status,
                        'assigned_at' => $assignment->assigned_at,
                        'started_at' => $assignment->started_at,
                        'completed_at' => $assignment->completed_at,
                    ];
                })
            ];

            return response()->json([
                'success' => true,
                'data' => $transformedAgent
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve CS agent details',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get all assignments for a specific CS Agent
     * GET /api/admin/cs-agents/{id}/assignments
     */
    public function getAssignments(Request $request, int $id): JsonResponse
    {
        try {
            // Verify CS Agent exists
            $csAgent = User::csAgents()->find($id);
            if (!$csAgent) {
                return response()->json([
                    'success' => false,
                    'message' => 'CS Agent not found'
                ], 404);
            }

            $perPage = $request->input('per_page', 15);
            $status = $request->input('status');
            $sort = $request->input('sort', '-assigned_at');

            // Parse sort parameter
            $sortDirection = 'desc';
            $sortField = 'assigned_at';
            if (str_starts_with($sort, '-')) {
                $sortField = substr($sort, 1);
                $sortDirection = 'desc';
            } else {
                $sortField = $sort;
                $sortDirection = 'asc';
            }

            $query = CSAgentPropertyAssign::with([
                'property:id,title,description,type,price,property_state,owner_id,created_at',
                'property.owner:id,first_name,last_name,email,phone_number',
                'property.images:id,property_id,image_url,order_index',
                'assignedBy:id,first_name,last_name,email'
            ])
            ->where('cs_agent_id', $id);

            // Apply status filter
            if ($status) {
                $query->where('status', $status);
            }

            $assignments = $query->orderBy($sortField, $sortDirection)
                ->paginate($perPage);

            return response()->json([
                'success' => true,
                'data' => CSAgentAssignmentResource::collection($assignments->items()),
                'links' => [
                    'first' => $assignments->url(1),
                    'last' => $assignments->url($assignments->lastPage()),
                    'prev' => $assignments->previousPageUrl(),
                    'next' => $assignments->nextPageUrl(),
                ],
                'meta' => [
                    'current_page' => $assignments->currentPage(),
                    'from' => $assignments->firstItem(),
                    'last_page' => $assignments->lastPage(),
                    'per_page' => $assignments->perPage(),
                    'to' => $assignments->lastItem(),
                    'total' => $assignments->total(),
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve CS agent assignments',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
