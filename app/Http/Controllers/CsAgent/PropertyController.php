<?php

namespace App\Http\Controllers\CsAgent;

use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\CSAgentAssignmentResource;
use App\Http\Resources\CsAgent\PropertyDetailResource;
use App\Models\CSAgentPropertyAssign;
use App\Models\Property;
use App\Models\AuditLog;
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

    /**
     * Get detailed view of a specific assigned property
     * GET /api/cs-agent/properties/{id}
     */
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

            // Check if the property is assigned to this CS agent
            $assignment = CSAgentPropertyAssign::where('property_id', $id)
                ->where('cs_agent_id', $user->id)
                ->with([
                    'assignedBy:id,first_name,last_name,email'
                ])
                ->first();

            if (!$assignment) {
                return response()->json([
                    'success' => false,
                    'message' => 'Property not found or not assigned to you.'
                ], 404);
            }

            // Get the property with all relevant relationships
            $property = Property::with([
                'owner:id,first_name,last_name,email,phone_number,status,created_at',
                'images' => function ($query) {
                    $query->orderBy('order_index', 'asc');
                },
                'documents',
                'features',
                'reviews' => function ($query) {
                    $query->with('user:id,first_name,last_name')
                          ->orderBy('created_at', 'desc')
                          ->limit(10);
                },
                'currentAssignment' => function ($query) use ($user) {
                    $query->where('cs_agent_id', $user->id)
                          ->with('assignedBy:id,first_name,last_name');
                }
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
                'data' => new PropertyDetailResource($property),
                'assignment' => new CSAgentAssignmentResource($assignment)
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve property details',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get verification timeline/history for a specific property
     * GET /api/cs-agent/properties/{property}/timeline
     */
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

            // Check if the property is assigned to this CS agent
            $assignment = CSAgentPropertyAssign::where('property_id', $property->id)
                ->where('cs_agent_id', $user->id)
                ->first();

            if (!$assignment) {
                return response()->json([
                    'success' => false,
                    'message' => 'Property not found or not assigned to you.'
                ], 404);
            }

            $timeline = collect();

            // 1. Assignment Events - Get all assignments for this property
            $assignments = CSAgentPropertyAssign::where('property_id', $property->id)
                ->with(['csAgent:id,first_name,last_name', 'assignedBy:id,first_name,last_name'])
                ->orderBy('assigned_at', 'desc')
                ->get();

            foreach ($assignments as $assign) {
                // Assignment created
                $timeline->push([
                    'id' => 'assignment_' . $assign->id,
                    'action' => 'Assignment Created',
                    'description' => 'Property assigned for verification',
                    'type' => 'assignment',
                    'timestamp' => $assign->assigned_at,
                    'user_name' => $assign->assignedBy ? $assign->assignedBy->first_name . ' ' . $assign->assignedBy->last_name : 'System',
                    'user_role' => 'admin',
                    'metadata' => [
                        'assigned_to' => $assign->csAgent->first_name . ' ' . $assign->csAgent->last_name,
                        'priority' => $assign->metadata['priority'] ?? 'normal',
                        'notes' => $assign->notes
                    ]
                ]);

                // Status changes
                if ($assign->started_at) {
                    $timeline->push([
                        'id' => 'started_' . $assign->id,
                        'action' => 'Work Started',
                        'description' => 'Agent started working on property verification',
                        'type' => 'status_change',
                        'timestamp' => $assign->started_at,
                        'user_name' => $assign->csAgent->first_name . ' ' . $assign->csAgent->last_name,
                        'user_role' => 'agent',
                        'metadata' => [
                            'old_status' => 'pending',
                            'new_status' => 'in_progress'
                        ]
                    ]);
                }

                if ($assign->completed_at) {
                    $timeline->push([
                        'id' => 'completed_' . $assign->id,
                        'action' => $assign->status === 'completed' ? 'Verification Completed' : 'Verification Rejected',
                        'description' => $assign->status === 'completed' 
                            ? 'Property verification successfully completed'
                            : 'Property verification was rejected',
                        'type' => 'status_change',
                        'timestamp' => $assign->completed_at,
                        'user_name' => $assign->csAgent->first_name . ' ' . $assign->csAgent->last_name,
                        'user_role' => 'agent',
                        'metadata' => [
                            'old_status' => 'in_progress',
                            'new_status' => $assign->status,
                            'notes' => $assign->notes
                        ]
                    ]);
                }
            }

            // 2. Document Events - Get property documents
            $documents = $property->documents()
                ->orderBy('created_at', 'desc')
                ->get();

            foreach ($documents as $document) {
                $timeline->push([
                    'id' => 'document_' . $document->id,
                    'action' => 'Document Uploaded',
                    'description' => 'Verification document uploaded: ' . ($document->document_type ?? 'Document'),
                    'type' => 'document',
                    'timestamp' => $document->created_at,
                    'user_name' => 'System', // Since we don't have uploader info in the model
                    'user_role' => 'system',
                    'metadata' => [
                        'document_type' => $document->document_type ?? 'Unknown',
                        'document_name' => $document->original_filename ?? 'Document',
                        'file_size' => $document->size ?? null
                    ]
                ]);
            }

            // 3. Audit Log Events - Get audit logs related to this property
            $auditLogs = \App\Models\AuditLog::where('entity', 'property')
                ->where('details->property_id', $property->id)
                ->with('user:id,first_name,last_name')
                ->orderBy('created_at', 'desc')
                ->get();

            foreach ($auditLogs as $log) {
                $timeline->push([
                    'id' => 'audit_' . $log->id,
                    'action' => ucfirst(str_replace('_', ' ', $log->action)),
                    'description' => $this->formatAuditLogDescription($log),
                    'type' => 'audit',
                    'timestamp' => $log->created_at,
                    'user_name' => $log->user ? 
                        $log->user->first_name . ' ' . $log->user->last_name : 
                        'System',
                    'user_role' => $log->user && $log->user->isCsAgent() ? 'agent' : 'admin',
                    'metadata' => $log->details ?? []
                ]);
            }

            // Sort timeline by timestamp (newest first)
            $sortedTimeline = $timeline->sortByDesc('timestamp')->values();

            // Format timestamps for human reading
            $formattedTimeline = $sortedTimeline->map(function ($item) {
                $item['timestamp_human'] = \Carbon\Carbon::parse($item['timestamp'])->diffForHumans();
                $item['timestamp_formatted'] = \Carbon\Carbon::parse($item['timestamp'])->format('M j, Y g:i A');
                return $item;
            });

            return response()->json([
                'success' => true,
                'data' => $formattedTimeline,
                'meta' => [
                    'total_events' => $formattedTimeline->count(),
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

    /**
     * Format audit log description for better readability
     */
    private function formatAuditLogDescription(\App\Models\AuditLog $log): string
    {
        $action = $log->action;
        $details = $log->details ?? [];

        return match($action) {
            'property_created' => 'Property was created',
            'property_updated' => 'Property details were updated',
            'property_status_changed' => sprintf(
                'Property status changed from %s to %s',
                $details['old_status'] ?? 'unknown',
                $details['new_status'] ?? 'unknown'
            ),
            'property_assigned' => sprintf(
                'Property assigned to %s',
                $details['agent_name'] ?? 'agent'
            ),
            'property_unassigned' => 'Property assignment removed',
            'verification_started' => 'Property verification process started',
            'verification_completed' => 'Property verification completed',
            'verification_rejected' => 'Property verification rejected',
            'notes_added' => 'Notes added to property',
            default => ucfirst(str_replace('_', ' ', $action))
        };
    }

    /**
     * Add a note to the property timeline
     * POST /api/cs-agent/properties/{property}/notes
     */
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

            // Check if the property is assigned to this CS agent
            $assignment = CSAgentPropertyAssign::where('property_id', $property->id)
                ->where('cs_agent_id', $user->id)
                ->first();

            if (!$assignment) {
                return response()->json([
                    'success' => false,
                    'message' => 'Property not found or not assigned to you.'
                ], 404);
            }

            // Validate the request
            $request->validate([
                'note' => 'required|string|max:1000',
            ]);

            // Create audit log entry for the note
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

            // Format the response as a timeline entry
            $timelineEntry = [
                'id' => 'note_' . $auditLog->id,
                'action' => 'Note Added',
                'description' => 'Agent added a note: ' . $request->note,
                'type' => 'note_added',
                'timestamp' => $auditLog->created_at,
                'timestamp_human' => $auditLog->created_at->diffForHumans(),
                'timestamp_formatted' => $auditLog->created_at->format('M j, Y g:i A'),
                'user_name' => $user->first_name . ' ' . $user->last_name,
                'user_role' => 'agent',
                'metadata' => [
                    'note' => $request->note,
                    'audit_id' => $auditLog->id
                ]
            ];

            return response()->json([
                'success' => true,
                'data' => $timelineEntry,
                'message' => 'Note added successfully to property timeline'
            ]);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to add note to timeline',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
