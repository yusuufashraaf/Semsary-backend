<?php

namespace App\Http\Controllers\CsAgent;

use App\Events\PropertyUpdated;
use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\CSAgentAssignmentResource;
use App\Http\Resources\CsAgent\PropertyDetailResource;
use App\Models\CSAgentPropertyAssign;
use App\Models\Property;
use App\Models\AuditLog;
use App\Notifications\PropertyFeedbackNotification;
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
        $propertyState = $request->input('property_state');
        $sort = $request->input('sort', '-created_at');

        $sortDirection = 'desc';
        $sortField = 'created_at';
        if (str_starts_with($sort, '-')) {
            $sortField = substr($sort, 1);
            $sortDirection = 'desc';
        } else {
            $sortField = $sort;
            $sortDirection = 'asc';
        }

        // Query all properties
        $query = Property::with([
            'owner:id,first_name,last_name,email,phone_number,status',
            'images:id,property_id,image_url,order_index'
        ]);

        // Exclude properties owned by the current user
        $query->where('owner_id', '<>', $user->id);

        // Filter by property_state
        if ($propertyState) {
            $query->where('property_state', $propertyState);
        }

        // Filter by status if needed (e.g., published/unpublished)
        if ($status) {
            $query->where('status', $status);
        }

        $properties = $query->orderBy($sortField, $sortDirection)
            ->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => PropertyDetailResource::collection($properties->items()),
            'links' => [
                'first' => $properties->url(1),
                'last' => $properties->url($properties->lastPage()),
                'prev' => $properties->previousPageUrl(),
                'next' => $properties->nextPageUrl(),
            ],
            'meta' => [
                'current_page' => $properties->currentPage(),
                'from' => $properties->firstItem(),
                'last_page' => $properties->lastPage(),
                'per_page' => $properties->perPage(),
                'to' => $properties->lastItem(),
                'total' => $properties->total(),
            ]
        ]);
    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'message' => 'Failed to retrieve properties',
            'error' => $e->getMessage()
        ], 500);
    }
}
public function updateState(Request $request, Property $property): JsonResponse
{
    $request->validate([
        'status' => 'required|string|in:Valid,Rejected',
        'feedback' => 'nullable|string|max:500',
    ]);

    try {
        $property->property_state = $request->input('status');
        $property->save();


         if ($property->owner) {
            $property->owner->notify(
                new PropertyFeedbackNotification($property, $request->status, $request->feedback ?? '')
            );
        }
        broadcast(new PropertyUpdated($property))->toOthers();

        return response()->json([
            'success' => true,
            'message' => 'Property state updated successfully',
            'data' => [
                'id' => $property->id,
                'property_state' => $property->property_state,
            ]
        ]);
    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'message' => 'Failed to update property state',
            'error' => $e->getMessage()
        ], 500);
    }
}

public function show(Request $request, int $id): JsonResponse
{
    try {
        $user = auth()->user();

        if (!$user->isCsAgent()) {
            return response()->json([
                'success' => false,
                'message' => 'Access denied. User is not a CS Agent.'
            ], 403);
        }

        $property = Property::with([
            'owner:id,first_name,last_name,email,phone_number,status,created_at',
            'images' => fn($q) => $q->orderBy('order_index', 'asc'),
            'documents',
            'features',
            'reviews' => fn($q) => $q->with('user:id,first_name,last_name')->orderBy('created_at', 'desc')->limit(10),
        ])
        ->withCount(['reviews', 'bookings'])
        ->find($id);

        if (!$property) {
            return response()->json([
                'success' => false,
                'message' => 'Property not found.'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => new PropertyDetailResource($property)
        ]);
    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'message' => 'Failed to retrieve property details',
            'error' => $e->getMessage()
        ], 500);
    }
}

public function getTimeline(Request $request, Property $property): JsonResponse
{
    try {
        $user = auth()->user();

        if (!$user->isCsAgent()) {
            return response()->json([
                'success' => false,
                'message' => 'Access denied. User is not a CS Agent.'
            ], 403);
        }

        $timeline = collect();

        // Assignment Events (all agents)
        $assignments = CSAgentPropertyAssign::where('property_id', $property->id)
            ->with(['csAgent:id,first_name,last_name', 'assignedBy:id,first_name,last_name'])
            ->orderBy('assigned_at', 'desc')
            ->get();

        foreach ($assignments as $assign) {
            $timeline->push([
                'id' => 'assignment_' . $assign->id,
                'action' => 'Assignment Created',
                'description' => 'Property assigned for verification',
                'type' => 'assignment',
                'timestamp' => $assign->assigned_at,
                'user_name' => $assign->assignedBy?->first_name . ' ' . $assign->assignedBy?->last_name ?? 'System',
                'user_role' => 'admin',
            ]);
        }

        // Document Events
        foreach ($property->documents as $document) {
            $timeline->push([
                'id' => 'document_' . $document->id,
                'action' => 'Document Uploaded',
                'description' => 'Document uploaded: ' . ($document->document_type ?? 'Document'),
                'type' => 'document',
                'timestamp' => $document->created_at,
                'user_name' => 'System',
                'user_role' => 'system',
            ]);
        }

        $sortedTimeline = $timeline->sortByDesc('timestamp')->values();

        return response()->json([
            'success' => true,
            'data' => $sortedTimeline,
            'meta' => [
                'total_events' => $sortedTimeline->count(),
                'property_id' => $property->id,
                'property_title' => $property->title
            ]
        ]);
    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'message' => 'Failed to retrieve property timeline',
            'error' => $e->getMessage()
        ], 500);
    }
}

public function addNote(Request $request, Property $property): JsonResponse
{
    try {
        $user = auth()->user();

        if (!$user->isCsAgent()) {
            return response()->json([
                'success' => false,
                'message' => 'Access denied. User is not a CS Agent.'
            ], 403);
        }

        $request->validate([
            'note' => 'required|string|max:1000',
        ]);

        $auditLog = AuditLog::create([
            'user_id' => $user->id,
            'entity' => 'property',
            'action' => 'notes_added',
            'details' => [
                'property_id' => $property->id,
                'property_title' => $property->title,
                'note' => $request->note,
                'agent_name' => $user->first_name . ' ' . $user->last_name,
                'timestamp' => now()
            ]
        ]);

        $timelineEntry = [
            'id' => 'note_' . $auditLog->id,
            'action' => 'Note Added',
            'description' => 'Agent added a note: ' . $request->note,
            'type' => 'note_added',
            'timestamp' => $auditLog->created_at,
            'user_name' => $user->first_name . ' ' . $user->last_name,
            'user_role' => 'agent',
        ];

        return response()->json([
            'success' => true,
            'data' => $timelineEntry,
            'message' => 'Note added successfully to property timeline'
        ]);
    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'message' => 'Failed to add note',
            'error' => $e->getMessage()
        ], 500);
    }
}
}
