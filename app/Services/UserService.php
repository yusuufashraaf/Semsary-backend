<?php

namespace App\Services;

use App\Models\User;
use App\Models\AdminAction;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Log;
use Exception;

class UserService
{
    /**
     * Get users with filters and pagination
     */
    public function getUsersWithFilters(array $filters, int $perPage = 15): LengthAwarePaginator
    {
        try {
            // Simplified query without relationships for debugging
            $query = User::query()->withFilters($filters);

            // Apply sorting
            $sortBy = $filters['sort_by'] ?? 'created_at';
            $sortOrder = $filters['sort_order'] ?? 'desc';

            $allowedSortFields = ['created_at', 'first_name', 'email', 'status', 'role'];
            if (in_array($sortBy, $allowedSortFields)) {
                $query->orderBy($sortBy, $sortOrder);
            }

            return $query->paginate($perPage);
        } catch (Exception $e) {
            Log::error('Error getting users with filters: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Get user by ID with relationships
     */
    public function getUserById(int $id): ?User
    {
        try {
            // First, get the basic user
            $user = User::find($id);

            if (!$user) {
                return null;
            }

            // Try to load relationships one by one, handling failures gracefully
            try {
                $user->loadCount('properties');
            } catch (Exception $e) {
                Log::info('Properties count failed: ' . $e->getMessage());
            }

            try {
                $user->loadCount('transactions');
            } catch (Exception $e) {
                Log::info('Transactions count failed: ' . $e->getMessage());
            }

            try {
                $user->load(['properties' => function ($query) {
                    $query->latest()->limit(5);
                }]);
            } catch (Exception $e) {
                Log::info('Properties relationship failed: ' . $e->getMessage());
            }

            try {
                $user->load(['transactions' => function ($query) {
                    $query->latest()->limit(10);
                }]);
            } catch (Exception $e) {
                Log::info('Transactions relationship failed: ' . $e->getMessage());
            }

            try {
                $user->load(['adminActions.admin' => function ($query) {
                    $query->select('id', 'first_name', 'last_name', 'email');
                }]);
            } catch (Exception $e) {
                Log::info('AdminActions relationship failed: ' . $e->getMessage());
            }

            return $user;
        } catch (Exception $e) {
            Log::error('Error getting user by ID: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Activate user
     */
    public function activateUser(int $userId, int $adminId, ?string $reason = null): bool
    {
        try {
            $user = User::findOrFail($userId);
            return $user->activateUser($adminId, $reason);
        } catch (Exception $e) {
            Log::error('Error activating user: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Suspend user
     */
    public function suspendUser(int $userId, int $adminId, ?string $reason = null): bool
    {
        try {
            $user = User::findOrFail($userId);
            return $user->suspendUser($adminId, $reason);
        } catch (Exception $e) {
            Log::error('Error suspending user: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Block user
     */
    public function blockUser(int $userId, int $adminId, ?string $reason = null): bool
    {
        try {
            $user = User::findOrFail($userId);
            return $user->blockUser($adminId, $reason);
        } catch (Exception $e) {
            Log::error('Error blocking user: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Get user activity (admin actions performed on this user)
     */
    public function getUserActivity(int $userId): Collection
    {
        try {
            return AdminAction::onUser($userId)
                ->with('admin:id,first_name,last_name,email')
                ->orderBy('created_at', 'desc')
                ->limit(20)
                ->get();
        } catch (Exception $e) {
            Log::error('Error getting user activity: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Search users
     */
    public function searchUsers(string $term, int $limit = 10): Collection
    {
        try {
            return User::where(function ($query) use ($term) {
                $query->where('first_name', 'like', "%{$term}%")
                      ->orWhere('last_name', 'like', "%{$term}%")
                      ->orWhere('email', 'like', "%{$term}%")
                      ->orWhere('phone_number', 'like', "%{$term}%");
            })
            ->select('id', 'first_name', 'last_name', 'email', 'role', 'status')
            ->limit($limit)
            ->get();
        } catch (Exception $e) {
            Log::error('Error searching users: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Get user statistics for admin dashboard
     */
    public function getUserStatistics(): array
    {
        try {
            return [
                'total_users' => User::count(),
                'active_users' => User::active()->count(),
                'pending_users' => User::pending()->count(),
                'suspended_users' => User::suspended()->count(),
                'users_by_role' => User::selectRaw('role, count(*) as count')
                    ->groupBy('role')
                    ->pluck('count', 'role')
                    ->toArray(),
                'recent_registrations' => User::where('created_at', '>=', now()->subDays(30))
                    ->count(),
                'recent_admin_actions' => AdminAction::recent(30)->count(),
            ];
        } catch (Exception $e) {
            Log::error('Error getting user statistics: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Get users requiring attention
     */
    public function getUsersRequiringAttention(): array
    {
        try {
            return [
                'pending_users' => User::pending()->count(),
                'suspended_users' => User::suspended()->count(),
                'unverified_users' => User::whereNull('email_verified_at')
                    ->orWhereNull('phone_verified_at')
                    ->count(),
            ];
        } catch (Exception $e) {
            Log::error('Error getting users requiring attention: ' . $e->getMessage());
            throw $e;
        }
    }
}
