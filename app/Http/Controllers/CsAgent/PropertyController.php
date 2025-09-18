<?php

namespace App\Http\Controllers\CsAgent;

use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\CSAgentAssignmentResource;
use App\Models\CSAgentPropertyAssign;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PropertyController extends Controller
{
    /**
     * Get assigned properties for the current CS Agent
     * GET /api/cs-agent/properties
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $user = auth()->user();

            if (!$user->isCsAgent()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Access denied. User is not a CS Agent.'
                ], 403);
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
                'property.images:id,property_id,image_url,order_index'
            ])
            ->where('cs_agent_id', $user->id);

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
                'message' => 'Failed to retrieve assigned properties',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
