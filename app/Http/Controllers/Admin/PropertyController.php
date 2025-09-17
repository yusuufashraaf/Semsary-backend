<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\PropertyFilterRequest;
use App\Http\Requests\Admin\PropertyStatusRequest;
use App\Http\Resources\Admin\PropertyManagementResource;
use App\Http\Resources\Admin\PropertyDetailResource;
use App\Services\Admin\PropertyManagementService;
use App\Models\Property;
use App\Models\AuditLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Exception;

/**
 * SEM-62: Admin Properties Management API Implementation
 *
 * This controller handles all admin operations for property management
 * including viewing, filtering, status management, and detailed property operations
 */
class PropertyController extends Controller
{
    protected PropertyManagementService $propertyService;

    public function __construct(PropertyManagementService $propertyService)
    {
        $this->propertyService = $propertyService;
    }

    /**
     * Get paginated list of all properties with admin filters
     *
     * @param PropertyFilterRequest $request
     * @return JsonResponse
     */
    public function index(PropertyFilterRequest $request): JsonResponse
    {
        try {
            $filters = $request->validated();
            $properties = $this->propertyService->getFilteredProperties($filters);

            // Log admin property list access
            AuditLog::log(
                auth()->id(),
                'Property',
                'admin_list_properties',
                [
                    'filters_applied' => $filters,
                    'results_count' => $properties->count(),
                    'timestamp' => now()
                ]
            );

            return response()->json([
                'status' => 'success',
                'data' => [
                    'properties' => PropertyManagementResource::collection($properties->items()),
                    'pagination' => [
                        'current_page' => $properties->currentPage(),
                        'last_page' => $properties->lastPage(),
                        'per_page' => $properties->perPage(),
                        'total' => $properties->total(),
                        'has_more_pages' => $properties->hasMorePages()
                    ]
                ]
            ]);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve properties',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get detailed view of a specific property for admin
     *
     * @param int $id
     * @return JsonResponse
     */
    public function show(int $id): JsonResponse
    {
        try {
            $property = $this->propertyService->getPropertyWithDetails($id);

            if (!$property) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Property not found'
                ], 404);
            }

            // Log admin property view
            AuditLog::log(
                auth()->id(),
                'Property',
                'admin_view_property',
                [
                    'property_id' => $id,
                    'property_title' => $property->title,
                    'timestamp' => now()
                ]
            );

            return response()->json([
                'status' => 'success',
                'data' => new PropertyDetailResource($property)
            ]);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve property details',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update property status (approve, reject, etc.)
     *
     * @param PropertyStatusRequest $request
     * @param int $id
     * @return JsonResponse
     */
    public function updateStatus(PropertyStatusRequest $request, int $id): JsonResponse
    {
        try {
            $validated = $request->validated();
            $property = $this->propertyService->updatePropertyStatus($id, $validated);

            if (!$property) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Property not found'
                ], 404);
            }

            // Log admin property status change
            AuditLog::log(
                auth()->id(),
                'Property',
                'admin_status_change',
                [
                    'property_id' => $id,
                    'old_status' => $property->getOriginal('property_state'),
                    'new_status' => $property->property_state,
                    'reason' => $validated['reason'] ?? null,
                    'timestamp' => now()
                ]
            );

            return response()->json([
                'status' => 'success',
                'message' => 'Property status updated successfully',
                'data' => new PropertyManagementResource($property)
            ]);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to update property status',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Bulk approve multiple properties
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function bulkApprove(Request $request): JsonResponse
    {
        $request->validate([
            'property_ids' => 'required|array|min:1',
            'property_ids.*' => 'integer|exists:properties,id',
            'reason' => 'nullable|string|max:500'
        ]);

        try {
            $result = $this->propertyService->bulkUpdateStatus(
                $request->property_ids,
                'Valid',
                $request->reason
            );

            // Log bulk approve action
            AuditLog::log(
                auth()->id(),
                'Property',
                'admin_bulk_approve',
                [
                    'property_ids' => $request->property_ids,
                    'count' => count($request->property_ids),
                    'reason' => $request->reason,
                    'timestamp' => now()
                ]
            );

            return response()->json([
                'status' => 'success',
                'message' => "Successfully approved {$result['updated_count']} properties",
                'data' => $result
            ]);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to bulk approve properties',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Bulk reject multiple properties
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function bulkReject(Request $request): JsonResponse
    {
        $request->validate([
            'property_ids' => 'required|array|min:1',
            'property_ids.*' => 'integer|exists:properties,id',
            'reason' => 'required|string|max:500'
        ]);

        try {
            $result = $this->propertyService->bulkUpdateStatus(
                $request->property_ids,
                'Invalid',
                $request->reason
            );

            // Log bulk reject action
            AuditLog::log(
                auth()->id(),
                'Property',
                'admin_bulk_reject',
                [
                    'property_ids' => $request->property_ids,
                    'count' => count($request->property_ids),
                    'reason' => $request->reason,
                    'timestamp' => now()
                ]
            );

            return response()->json([
                'status' => 'success',
                'message' => "Successfully rejected {$result['updated_count']} properties",
                'data' => $result
            ]);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to bulk reject properties',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get properties statistics for admin dashboard
     *
     * @return JsonResponse
     */
    public function getStatistics(): JsonResponse
    {
        try {
            $stats = $this->propertyService->getPropertyStatistics();

            return response()->json([
                'status' => 'success',
                'data' => $stats
            ]);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve property statistics',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Search properties by various criteria
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function search(Request $request): JsonResponse
    {
        $request->validate([
            'search' => 'required|string|min:2|max:100',
            'type' => 'nullable|in:title,owner,location,id',
            'per_page' => 'nullable|integer|min:5|max:100'
        ]);

        try {
            $results = $this->propertyService->searchProperties(
                $request->search,
                $request->type ?? 'title',
                $request->per_page ?? 15
            );

            // Log search activity
            AuditLog::log(
                auth()->id(),
                'Property',
                'admin_search_properties',
                [
                    'search_term' => $request->search,
                    'search_type' => $request->type ?? 'title',
                    'results_count' => $results->count(),
                    'timestamp' => now()
                ]
            );

            return response()->json([
                'status' => 'success',
                'data' => [
                    'properties' => PropertyManagementResource::collection($results->items()),
                    'pagination' => [
                        'current_page' => $results->currentPage(),
                        'last_page' => $results->lastPage(),
                        'per_page' => $results->perPage(),
                        'total' => $results->total(),
                        'has_more_pages' => $results->hasMorePages()
                    ]
                ]
            ]);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to search properties',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get properties requiring admin attention
     *
     * @return JsonResponse
     */
    public function requiresAttention(): JsonResponse
    {
        try {
            $properties = $this->propertyService->getPropertiesRequiringAttention();

            return response()->json([
                'status' => 'success',
                'data' => PropertyManagementResource::collection($properties)
            ]);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve properties requiring attention',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Force delete a property (admin only)
     *
     * @param int $id
     * @return JsonResponse
     */
    public function destroy(int $id): JsonResponse
    {
        try {
            $property = Property::findOrFail($id);
            $propertyData = [
                'id' => $property->id,
                'title' => $property->title,
                'owner_id' => $property->owner_id
            ];

            $result = $this->propertyService->deleteProperty($id);

            if (!$result) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Property not found or cannot be deleted'
                ], 404);
            }

            // Log property deletion
            AuditLog::log(
                auth()->id(),
                'Property',
                'admin_delete_property',
                [
                    'deleted_property' => $propertyData,
                    'timestamp' => now()
                ]
            );

            return response()->json([
                'status' => 'success',
                'message' => 'Property deleted successfully'
            ]);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to delete property',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
