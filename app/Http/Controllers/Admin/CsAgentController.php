<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\User;
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
}
