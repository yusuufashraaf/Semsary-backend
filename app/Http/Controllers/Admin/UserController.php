<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\UserService;
use App\Http\Requests\UserStatusRequest;
use App\Http\Requests\UserFilterRequest;
use App\Http\Resources\UserResource;
use App\Http\Resources\Admin\UserDetailResource;
use App\Http\Resources\Admin\UserStatisticsResource;
use App\Http\Resources\Admin\AdminActionResource;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Exception;

class UserController extends Controller
{
    protected UserService $userService;

    public function __construct(UserService $userService)
    {
        $this->userService = $userService;
    }

    /**
     * Display a listing of users with filters
     */
    public function index(UserFilterRequest $request): JsonResponse
    {
        try {
            $filters = $request->validated();
            $perPage = $filters['per_page'] ?? 15;

            $users = $this->userService->getUsersWithFilters($filters, $perPage);

            return response()->json([
                'status' => 'success',
                'message' => 'Users retrieved successfully',
                'data' => UserResource::collection($users),
                'allusers' => UserResource::collection($users),
                'pagination' => [
                    'current_page' => $users->currentPage(),
                    'last_page' => $users->lastPage(),
                    'per_page' => $users->perPage(),
                    'total' => $users->total(),
                ],
            ]);

        } catch (Exception $e) {
            Log::error('Error retrieving users: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve users',
            ], 500);
        }
    }

    /**
     * Display the specified user
     */
    public function show(int $id): JsonResponse
    {
        try {
            $user = $this->userService->getUserById($id);

            if (!$user) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'User not found',
                ], 404);
            }

            return response()->json([
                'status' => 'success',
                'message' => 'User retrieved successfully',
                'data' => new UserDetailResource($user),
            ]);

        } catch (Exception $e) {
            Log::error('Error retrieving user: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve user',
            ], 500);
        }
    }

    /**
     * Activate user
     */
    public function activate(UserStatusRequest $request, int $id): JsonResponse
    {
        try {
            $adminId = auth()->id();
            $reason = $request->input('reason');

            $success = $this->userService->activateUser($id, $adminId, $reason);

            if ($success) {
                return response()->json([
                    'status' => 'success',
                    'message' => 'User activated successfully',
                ]);
            }

            return response()->json([
                'status' => 'error',
                'message' => 'User is already active',
            ], 400);

        } catch (Exception $e) {
            Log::error('Error activating user: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to activate user',
            ], 500);
        }
    }

    /**
     * Suspend user
     */
    public function suspend(UserStatusRequest $request, int $id): JsonResponse
    {
        try {
            $adminId = auth()->id();
            $reason = $request->input('reason');

            $success = $this->userService->suspendUser($id, $adminId, $reason);

            if ($success) {
                return response()->json([
                    'status' => 'success',
                    'message' => 'User suspended successfully',
                ]);
            }

            return response()->json([
                'status' => 'error',
                'message' => 'User is already suspended',
            ], 400);

        } catch (Exception $e) {
            Log::error('Error suspending user: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to suspend user',
            ], 500);
        }
    }

    /**
     * Block user
     */
    public function block(UserStatusRequest $request, int $id): JsonResponse
    {
        try {
            $adminId = auth()->id();
            $reason = $request->input('reason');

            $success = $this->userService->blockUser($id, $adminId, $reason);

            if ($success) {
                return response()->json([
                    'status' => 'success',
                    'message' => 'User blocked successfully',
                ]);
            }

            return response()->json([
                'status' => 'error',
                'message' => 'User is already blocked',
            ], 400);

        } catch (Exception $e) {
            Log::error('Error blocking user: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to block user',
            ], 500);
        }
    }

    /**
     * Get user activity (admin actions performed on this user)
     */
    public function getUserActivity(int $id): JsonResponse
    {
        try {
            $activity = $this->userService->getUserActivity($id);

            return response()->json([
                'status' => 'success',
                'message' => 'User activity retrieved successfully',
                'data' => AdminActionResource::collection($activity),
            ]);

        } catch (Exception $e) {
            Log::error('Error retrieving user activity: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve user activity',
            ], 500);
        }
    }

    /**
     * Search users
     */
    public function search(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'term' => 'required|string|min:2|max:255',
                'limit' => 'nullable|integer|min:1|max:50'
            ]);

            $term = $request->input('term');
            $limit = $request->input('limit', 10);

            $users = $this->userService->searchUsers($term, $limit);

            return response()->json([
                'status' => 'success',
                'message' => 'Users found',
                'data' => UserResource::collection($users),
            ]);

        } catch (Exception $e) {
            Log::error('Error searching users: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to search users',
            ], 500);
        }
    }

    /**
     * Get user statistics
     */
    public function statistics(): JsonResponse
    {
        try {
            $stats = $this->userService->getUserStatistics();

            return response()->json([
                'status' => 'success',
                'message' => 'User statistics retrieved successfully',
                'data' => new UserStatisticsResource($stats),
            ]);

        } catch (Exception $e) {
            Log::error('Error retrieving user statistics: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve user statistics',
            ], 500);
        }
    }

    /**
     * Get users requiring attention
     */
    public function requiresAttention(): JsonResponse
    {
        try {
            $attention = $this->userService->getUsersRequiringAttention();

            return response()->json([
                'status' => 'success',
                'message' => 'Users requiring attention retrieved successfully',
                'data' => $attention,
            ]);

        } catch (Exception $e) {
            Log::error('Error retrieving users requiring attention: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve users requiring attention',
            ], 500);
        }
    }
public function changeRole(Request $request, int $id): JsonResponse
{
    $request->validate([
        'role' => 'required|string|in:admin,owner,agent,user',
        'reason' => 'nullable|string|max:255',
    ]);

    $adminId = auth()->id();
    $user = User::find($id);

    if (!$user) {
        return response()->json([
            'status' => 'error',
            'message' => 'User not found',
        ], 404);
    }

    if ($user->id === $adminId) {
        return response()->json([
            'status' => 'error',
            'message' => 'You cannot change your own role',
        ], 403);
    }

    // ✅ Just update the role in the users table
    $user->update(['role' => $request->role]);

    return response()->json([
        'status' => 'success',
        'message' => "User role updated successfully to {$request->role}",
        'data'   => new \App\Http\Resources\UserResource($user),
    ]);
}


}