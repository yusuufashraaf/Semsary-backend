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
use App\Notifications\CustomMessage;
use App\Enums\NotificationPurpose;
use App\Events\UserUpdated;
use App\Models\User;
use App\Models\UserNotification;
use App\Notifications\IdStatusUpdated;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification; // ADDED THIS LINE
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


    broadcast(new UserUpdated($user));

    return response()->json([
        'status' => 'success',
        'message' => "User role updated successfully to {$request->role}",
        'data'   => new \App\Http\Resources\UserResource($user),
    ]);
}

public function updateState($id, $status)
{
    // Validate the status parameter
    $validStatuses = ['active', 'suspended', 'pending'];
    if (!in_array($status, $validStatuses)) {
        return response()->json([
            'status' => 'error',
            'message' => 'Invalid status provided. Valid statuses: ' . implode(', ', $validStatuses)
        ], 400);
    }

    try {
        // Find the user
        $user = User::find($id);

        if (!$user) {
            return response()->json([
                'status' => 'error',
                'message' => 'User not found'
            ], 404);
        }
        $missingdata = false;
        if (!$user->phone_verified_at) {
            $missingdata = true;
        }
        if (!$user->email_verified_at) {
            $missingdata = true;
        }
        if ($user->id_state != "valid") {
            $missingdata = true;
        }

        // CORRECT WAY: Update using array or direct assignment
        if($missingdata && $status == "active"){
            $user->update(['status' => 'pending']);
        }
        else{
            $user->update(['status' => $status]);
        }
        broadcast(new UserUpdated($user));
        // OR alternative correct way:
        // $user->status = $status;
        // $user->save();

        // Log the action
        Log::info("User status updated", [
            'admin_id' => auth('api')->id(),
            'user_id' => $id,
            'old_status' => $user->getOriginal('status'),
            'new_status' => $status
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'User status updated successfully',
            'data' => [
                'user' => $user,
            ]
        ]);

    } catch (Exception $e) {
        Log::error("Error updating user status: " . $e->getMessage(), [
            'user_id' => $id,
            'status' => $status,
            'admin_id' => auth('api')->id()
        ]);

        return response()->json([
            'status' => 'error',
            'message' => 'Failed to update user status: ' . $e->getMessage()
        ], 500);
    }
}

public function updateIdState($id, $status)
{
    // Validate the status parameter
    $validStatuses = ['valid', 'rejected', 'pending'];
    if (!in_array($status, $validStatuses)) {
        return response()->json([
            'status' => 'error',
            'message' => 'Invalid status provided. Valid statuses: ' . implode(', ', $validStatuses)
        ], 400);
    }

    try {
        // Find the user
        $user = User::find($id);

        if (!$user) {
            return response()->json([
                'status' => 'error',
                'message' => 'User not found'
            ], 404);
        }

        // CORRECT WAY: Update using array or direct assignment
        $user->update(['id_state' => $status]);


        broadcast(new UserUpdated($user));
        // OR alternative correct way:
        // $user->status = $status;
        // $user->save();
        $user->notify(new IdStatusUpdated($status));

        // Log the action
        Log::info("User id status updated", [
            'admin_id' => auth('api')->id(),
            'user_id' => $id,
            'old_status' => $user->getOriginal('id_state'),
            'new_status' => $status
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'User status updated successfully',
            'data' => [
                'user_id' => $user->id,
                'new_status' => $user->id_state,
                'user_name' => $user->first_name . ' ' . $user->last_name
            ]
        ]);

    } catch (Exception $e) {
        Log::error("Error updating user ID status: " . $e->getMessage(), [
            'user_id' => $id,
            'id_state' => $status, // FIXED: changed $id_state to $status
            'admin_id' => auth('api')->id()
        ]);

        return response()->json([
            'status' => 'error',
            'message' => 'Failed to update user ID status: ' . $e->getMessage()
        ], 500);
    }
}

public function deleteUser($id)
{
    try {
        // Find the user
        $user = User::find($id);

        if (!$user) {
            return response()->json([
                'status' => 'error',
                'message' => 'User not found'
            ], 404);
        }


        // Store user info for logging before deletion
        $userInfo = [
            'user_id' => $user->id,
            'user_name' => $user->first_name . ' ' . $user->last_name,
            'email' => $user->email
        ];

        // Delete the user
        $user->delete();

        // Log the action
        Log::info("User deleted", [
            'admin_id' => auth('api')->id(),
            'deleted_user' => $userInfo
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'User deleted successfully',
            'data' => [
                'deleted_user' => $userInfo
            ]
        ]);

    } catch (Exception $e) {
        Log::error("Error deleting user: " . $e->getMessage(), [
            'user_id' => $id,
            'admin_id' => auth('api')->id()
        ]);

        return response()->json([
            'status' => 'error',
            'message' => 'Failed to delete user: ' . $e->getMessage()
        ], 500);
    }
}

public function updateRole($id, $status)
{
    // Validate the status parameter
    $validStatuses = ['admin', 'agent', 'user'];
    if (!in_array($status, $validStatuses)) {
        return response()->json([
            'status' => 'error',
            'message' => 'Invalid status provided. Valid statuses: ' . implode(', ', $validStatuses)
        ], 400);
    }

    try {
        // Find the user
        $user = User::find($id);

        if (!$user) {
            return response()->json([
                'status' => 'error',
                'message' => 'User not found'
            ], 404);
        }

        // CORRECT WAY: Update using array or direct assignment
        $user->update(['role' => $status]);

       broadcast(new UserUpdated($user));
        // OR alternative correct way:
        // $user->status = $status;
        // $user->save();

        // Log the action
        Log::info("User role updated", [
            'admin_id' => auth('api')->id(),
            'user_id' => $id,
            'old_status' => $user->getOriginal('role'),
            'new_status' => $status
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'User role updated successfully',
            'data' => [
                'user_id' => $user->id,
                'new_status' => $user->role,
                'user_name' => $user->first_name . ' ' . $user->last_name
            ]
        ]);

    } catch (Exception $e) {
        Log::error("Error updating role : " . $e->getMessage(), [
            'user_id' => $id,
            'id_state' => $status, // FIXED: changed $role to $status
            'admin_id' => auth('api')->id()
        ]);

        return response()->json([
            'status' => 'error',
            'message' => 'Failed to update user role: ' . $e->getMessage()
        ], 500);
    }
}

public function verifyAdmin($id)
{

    try {
        // Find the user
        $user = User::find($id);

        if (!$user) {
            return response()->json([
                'status' => 'error',
                'message' => 'User not found'
            ], 404);
        }

        // CORRECT WAY: Update using array or direct assignment
        $user->update([
            'role' => "admin",
            "email_verified_at" => now(),
            "phone_verified_at" =>now(),
            "id_state" => "valid"

    ]);

        // OR alternative correct way:
        // $user->status = $status;
        // $user->save();

        // Log the action
        Log::info("User role updated", [
            'admin_id' => auth('api')->id(),
            'user_id' => $id,
            'old_status' => $user->getOriginal('role'),
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Admin Created and Verified successfully',
            'data' => [
                'user_id' => $user->id,
                'new_status' => $user->role,
                'user_name' => $user->first_name . ' ' . $user->last_name
            ]
        ]);

    } catch (Exception $e) {
        Log::error("Error updating role : " . $e->getMessage(), [
            'user_id' => $id,
            'admin_id' => auth('api')->id()
        ]);

        return response()->json([
            'status' => 'error',
            'message' => 'Failed to update user role: ' . $e->getMessage()
        ], 500);
    }
}

public function notifyUser(Request $request, int $id)
{
    try {
        $user = User::find($id);

        if (!$user) {
            return response()->json([
                'status' => 'error',
                'message' => 'User not found'
            ], 404);
        }

        // Validate that message exists in request
        $request->validate([
            'message' => 'required|string'
        ]);
$Admin = auth('api')->user();
$dbnotification = UserNotification::create([
    'user_id' => $user->id,
    'sender_id' => $Admin->id, // Admin who sent the notification
    'entity_id' => $user->id, // The user entity being affected
    'purpose' => NotificationPurpose::USER_STATUS_UPDATE->value,
    'title' => "Account Status Updated",
    'message' => $request->message,
    'is_read' => false,
]);

        // Log the action
        Log::info("User notified", [
            'admin_id' => $Admin->id,
            'user_id' => $id,
            'message' => $request->message
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Notification sent successfully',
            'data' => [
                'user_id' => $user->id,
                'user_name' => $user->first_name . ' ' . $user->last_name,
                'UserNotification' => $dbnotification
            ]
        ]);

    } catch (Exception $e) {
        Log::error("Error sending notification: " . $e->getMessage(), [
            'user_id' => $id,
            'admin_id' => auth('api')->id(),
            'message' => $request->message ?? 'No message provided'
        ]);
    }
    }
}
}}

