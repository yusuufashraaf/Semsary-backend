<?php

namespace App\Services\Admin;

use App\Models\Property;
use App\Models\User;
use App\Models\Notification;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Carbon\Carbon;
use Illuminate\Support\Str;

/**
 * SEM-62: Property Management Service
 *
 * Handles all business logic for admin property management operations
 */
class PropertyManagementService
{
    /**
     * Get filtered properties for admin dashboard
     *
     * @param array $filters
     * @return LengthAwarePaginator
     */
    public function getFilteredProperties(array $filters): LengthAwarePaginator
    {
        $query = Property::with([
            'owner',
            'images',
            'features',
            'reviews',
            'bookings',
            'activeAssignment' => function ($query) {
                $query->with('csAgent:id,first_name,last_name,email');
            },
            'currentAssignment' => function ($query) {
                $query->with('csAgent:id,first_name,last_name,email');
            }
        ])
        ->withCount(['bookings', 'reviews']);

        // Apply filters
        if (!empty($filters['status'])) {
            if (is_array($filters['status'])) {
                $query->whereIn('property_state', $filters['status']);
            } else {
                $query->where('property_state', $filters['status']);
            }
        }

        if (!empty($filters['type'])) {
            if (is_array($filters['type'])) {
                $query->whereIn('type', $filters['type']);
            } else {
                $query->where('type', $filters['type']);
            }
        }

        if (!empty($filters['price_min'])) {
            $query->where('price', '>=', $filters['price_min']);
        }

        if (!empty($filters['price_max'])) {
            $query->where('price', '<=', $filters['price_max']);
        }

        if (!empty($filters['owner_id'])) {
            $query->where('owner_id', $filters['owner_id']);
        }

        if (!empty($filters['date_from'])) {
            $query->whereDate('created_at', '>=', $filters['date_from']);
        }

        if (!empty($filters['date_to'])) {
            $query->whereDate('created_at', '<=', $filters['date_to']);
        }

        if (!empty($filters['bedrooms'])) {
            $query->where('bedrooms', $filters['bedrooms']);
        }

        if (!empty($filters['bathrooms'])) {
            $query->where('bathrooms', $filters['bathrooms']);
        }

        if (!empty($filters['size_min'])) {
            $query->where('size', '>=', $filters['size_min']);
        }

        if (!empty($filters['size_max'])) {
            $query->where('size', '<=', $filters['size_max']);
        }

        // Location filtering (if location is provided as text)
        if (!empty($filters['location'])) {
            $query->whereJsonContains('location->address', $filters['location']);
        }

        // Relationship-based filters
        if (isset($filters['has_images'])) {
            if ($filters['has_images']) {
                $query->has('images');
            } else {
                $query->doesntHave('images');
            }
        }

        if (isset($filters['has_reviews'])) {
            if ($filters['has_reviews']) {
                $query->has('reviews');
            } else {
                $query->doesntHave('reviews');
            }
        }

        if (isset($filters['has_bookings'])) {
            if ($filters['has_bookings']) {
                $query->has('bookings');
            } else {
                $query->doesntHave('bookings');
            }
        }

        // Additional boolean filters
        if (isset($filters['featured_only']) && $filters['featured_only']) {
            $query->where('is_featured', true);
        }

        if (isset($filters['requires_attention']) && $filters['requires_attention']) {
            $query->where(function ($q) {
                $q->where('property_state', 'Pending')
                  ->orWhereDoesntHave('images')
                  ->orWhere(function ($subQ) {
                      $subQ->where('property_state', 'Pending')
                           ->where('created_at', '<', Carbon::now()->subDays(7));
                  });
            });
        }

        // Sorting
        $sortBy = $filters['sort_by'] ?? 'created_at';
        $sortOrder = $filters['sort_order'] ?? 'desc';

        switch ($sortBy) {
            case 'price':
                $query->orderBy('price', $sortOrder);
                break;
            case 'title':
                $query->orderBy('title', $sortOrder);
                break;
            case 'status':
                $query->orderBy('property_state', $sortOrder);
                break;
            case 'owner':
                $query->join('users', 'properties.owner_id', '=', 'users.id')
                      ->orderBy('users.first_name', $sortOrder);
                break;
            default:
                $query->orderBy('created_at', $sortOrder);
        }

        return $query->paginate($filters['per_page'] ?? 15);
    }

    /**
     * Get property with detailed information including relationships
     *
     * @param int $id
     * @return Property|null
     */
    public function getPropertyWithDetails(int $id): ?Property
    {
        return Property::with([
            'owner' => function ($query) {
                $query->select('id', 'first_name', 'last_name', 'email', 'phone_number', 'created_at', 'status');
            },
            'images',
            'features',
            'documents',
            'reviews' => function ($query) {
                $query->with('user:id,first_name,last_name')
                      ->orderBy('created_at', 'desc')
                      ->limit(10);
            },
            'bookings' => function ($query) {
                $query->with('customer:id,first_name,last_name')
                      ->orderBy('created_at', 'desc')
                      ->limit(10);
            },
            'transactions' => function ($query) {
                $query->orderBy('created_at', 'desc')
                      ->limit(5);
            },
            'activeAssignment' => function ($query) {
                $query->with([
                    'csAgent:id,first_name,last_name,email',
                    'assignedBy:id,first_name,last_name'
                ]);
            },
            'currentAssignment' => function ($query) {
                $query->with([
                    'csAgent:id,first_name,last_name,email',
                    'assignedBy:id,first_name,last_name'
                ]);
            }
        ])
        ->withCount(['bookings', 'reviews'])
        ->find($id);
    }

    /**
     * Update property status with notification to owner
     *
     * @param int $id
     * @param array $data
     * @return Property|null
     */
    public function updatePropertyStatus(int $id, array $data): ?Property
    {
        $property = Property::find($id);

        if (!$property) {
            return null;
        }

        DB::beginTransaction();

        try {
            $oldStatus = $property->property_state;
            $newStatus = $data['status'];

            $property->update([
                'property_state' => $newStatus
            ]);

            // Send notification to property owner
            $this->notifyPropertyOwner($property, $oldStatus, $newStatus, $data['reason'] ?? null);

            DB::commit();

            return $property->fresh();
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Bulk update property status
     *
     * @param array $propertyIds
     * @param string $status
     * @param string|null $reason
     * @return array
     */
    public function bulkUpdateStatus(array $propertyIds, string $status, ?string $reason = null): array
    {
        DB::beginTransaction();

        try {
            $properties = Property::whereIn('id', $propertyIds)->get();
            $updatedCount = 0;

            foreach ($properties as $property) {
                $oldStatus = $property->property_state;
                $property->update(['property_state' => $status]);

                // Send notification to owner
                $this->notifyPropertyOwner($property, $oldStatus, $status, $reason);
                $updatedCount++;
            }

            DB::commit();

            return [
                'updated_count' => $updatedCount,
                'total_requested' => count($propertyIds),
                'status' => $status,
                'reason' => $reason
            ];
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Get comprehensive property statistics
     *
     * @return array
     */
    public function getPropertyStatistics(): array
    {
        return Cache::remember('admin_property_statistics', config('admin.dashboard.property_stats_cache_ttl', 60), function () {
            return [
                'total_properties' => Property::count(),
                'by_status' => Property::select('property_state', DB::raw('count(*) as count'))
                    ->groupBy('property_state')
                    ->pluck('count', 'property_state')
                    ->toArray(),
                'by_type' => Property::select('type', DB::raw('count(*) as count'))
                    ->groupBy('type')
                    ->pluck('count', 'type')
                    ->toArray(),
                'pending_approval' => Property::where('property_state', 'Pending')->count(),
                'recently_added' => Property::where('created_at', '>=', Carbon::now()->subDays(7))->count(),
                'average_price' => round(Property::avg('price'), 2),
                'price_ranges' => [
                    'under_100k' => Property::where('price', '<', 100000)->count(),
                    '100k_500k' => Property::whereBetween('price', [100000, 500000])->count(),
                    '500k_1m' => Property::whereBetween('price', [500000, 1000000])->count(),
                    'over_1m' => Property::where('price', '>', 1000000)->count(),
                ],
                'monthly_additions' => $this->getMonthlyPropertyAdditions(),
                'properties_with_images' => Property::has('images')->count(),
                'properties_without_images' => Property::doesntHave('images')->count(),
                'top_owners' => $this->getTopPropertyOwners(),
                'revenue_generating' => Property::whereIn('property_state', ['Rented', 'Sold'])->count(),
            ];
        });
    }

    /**
     * Search properties by various criteria
     *
     * @param string $searchTerm
     * @param string $searchType
     * @param int $perPage
     * @return LengthAwarePaginator
     */
    public function searchProperties(string $searchTerm, string $searchType = 'title', int $perPage = 15): LengthAwarePaginator
    {
        $query = Property::with(['owner', 'images', 'features'])
            ->withCount(['bookings', 'reviews']);

        switch ($searchType) {
            case 'title':
                $query->where('title', 'LIKE', "%{$searchTerm}%");
                break;
            case 'owner':
                $query->whereHas('owner', function ($q) use ($searchTerm) {
                    $q->where('first_name', 'LIKE', "%{$searchTerm}%")
                      ->orWhere('last_name', 'LIKE', "%{$searchTerm}%")
                      ->orWhere('email', 'LIKE', "%{$searchTerm}%");
                });
                break;
            case 'location':
                $query->whereJsonContains('location->address', $searchTerm)
                      ->orWhereJsonContains('location->city', $searchTerm)
                      ->orWhereJsonContains('location->state', $searchTerm);
                break;
            case 'id':
                if (is_numeric($searchTerm)) {
                    $query->where('id', $searchTerm);
                } else {
                    // Return empty result if ID search with non-numeric term
                    $query->where('id', 0);
                }
                break;
            default:
                // Global search across multiple fields
                $query->where(function ($q) use ($searchTerm) {
                    $q->where('title', 'LIKE', "%{$searchTerm}%")
                      ->orWhere('description', 'LIKE', "%{$searchTerm}%")
                      ->orWhereJsonContains('location->address', $searchTerm)
                      ->orWhereHas('owner', function ($ownerQuery) use ($searchTerm) {
                          $ownerQuery->where('first_name', 'LIKE', "%{$searchTerm}%")
                                     ->orWhere('last_name', 'LIKE', "%{$searchTerm}%");
                      });
                });
        }

        return $query->orderBy('created_at', 'desc')->paginate($perPage);
    }

    /**
     * Get properties that require admin attention
     *
     * @return Collection
     */
    public function getPropertiesRequiringAttention(): Collection
    {
        return Property::with(['owner', 'images'])
            ->where(function ($query) {
                $query->where('property_state', 'Pending')
                      ->orWhere(function ($q) {
                          // Properties without images
                          $q->doesntHave('images');
                      })
                      ->orWhere(function ($q) {
                          // Properties with old pending status (over 7 days)
                          $q->where('property_state', 'Pending')
                            ->where('created_at', '<', Carbon::now()->subDays(7));
                      })
                      ->orWhere(function ($q) {
                          // Properties with issues (you can expand this logic)
                          $q->whereNull('owner_id');
                      });
            })
            ->orderBy('created_at', 'asc')
            ->limit(50)
            ->get();
    }

    /**
     * Soft delete property with cleanup
     *
     * @param int $id
     * @return bool
     */
    public function deleteProperty(int $id): bool
    {
        $property = Property::find($id);

        if (!$property) {
            return false;
        }

        DB::beginTransaction();

        try {
            // Cancel any active bookings
            $property->bookings()->where('status', 'confirmed')->update(['status' => 'cancelled']);

            // Notify property owner about deletion
            $this->notifyPropertyDeletion($property);

            // Delete the property
            $property->delete();

            DB::commit();
            return true;
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Get monthly property additions for statistics
     *
     * @return array
     */
    private function getMonthlyPropertyAdditions(): array
    {
        return Property::where('created_at', '>=', Carbon::now()->subMonths(12))
            ->select(
                DB::raw('DATE_FORMAT(created_at, "%Y-%m") as month'),
                DB::raw('COUNT(*) as count')
            )
            ->groupBy('month')
            ->orderBy('month')
            ->pluck('count', 'month')
            ->toArray();
    }

    /**
     * Get top property owners by property count
     *
     * @return array
     */
    private function getTopPropertyOwners(): array
    {
        return User::withCount('properties')
            ->having('properties_count', '>', 0)
            ->orderBy('properties_count', 'desc')
            ->limit(10)
            ->get(['id', 'first_name', 'last_name', 'email', 'properties_count'])
            ->map(function ($user) {
                return [
                    'id' => $user->id,
                    'name' => trim($user->first_name . ' ' . $user->last_name),
                    'email' => $user->email,
                    'properties_count' => $user->properties_count
                ];
            })
            ->toArray();
    }

    /**
     * Send notification to property owner about status change
     *
     * @param Property $property
     * @param string $oldStatus
     * @param string $newStatus
     * @param string|null $reason
     * @return void
     */
    private function notifyPropertyOwner(Property $property, string $oldStatus, string $newStatus, ?string $reason = null): void
    {
        if (!$property->owner) {
            return;
        }

        $statusMessages = [
            'Valid' => 'Your property has been approved and is now live.',
            'Invalid' => 'Your property has been rejected and requires attention.',
            'Pending' => 'Your property is under review.',
            'Rented' => 'Your property has been marked as rented.',
            'Sold' => 'Your property has been marked as sold.'
        ];

        $message = $statusMessages[$newStatus] ?? 'Your property status has been updated.';

        if ($reason) {
            $message .= " Reason: {$reason}";
        }

        DB::table('notifications')->insert([
            'id' => Str::uuid(),
            'type' => 'property_status_update',
            'notifiable_type' => 'User',
            'notifiable_id' => $property->owner_id,
            'data' => json_encode([
                'title' => 'Property Status Update',
                'message' => $message,
                'property_id' => $property->id,
                'property_title' => $property->title,
                'old_status' => $oldStatus,
                'new_status' => $newStatus,
                'reason' => $reason,
                'updated_by_admin' => auth()->id()
            ]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Send notification to property owner about property deletion
     *
     * @param Property $property
     * @return void
     */
    private function notifyPropertyDeletion(Property $property): void
    {
        if (!$property->owner) {
            return;
        }

        DB::table('notifications')->insert([
            'id' => Str::uuid(),
            'type' => 'property_deletion',
            'notifiable_type' => 'User',
            'notifiable_id' => $property->owner_id,
            'data' => json_encode([
                'title' => 'Property Deleted',
                'message' => "Your property '{$property->title}' has been removed from the platform by an administrator.",
                'property_id' => $property->id,
                'property_title' => $property->title,
                'deleted_by_admin' => auth()->id(),
                'deleted_at' => now()
            ]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
