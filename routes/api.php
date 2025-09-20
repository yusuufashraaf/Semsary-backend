<?php
use App\Http\Controllers\Api\FiltersController;
use App\Http\Controllers\Api\PropertyListController;
use App\Http\Controllers\Api\WishlistController;
use App\Http\Controllers\FeatureController;
use App\Http\Controllers\OwnerDashboardController;
use App\Http\Controllers\PropertyDetailsController;
use App\Http\Controllers\ReviewController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\PropertyController;
use App\Http\Controllers\Api\AuthenticationController;
use App\Http\Controllers\Api\ImageOfId;
use App\Http\Controllers\Api\forgetPasswordController;
use App\Http\Controllers\Api\GoogleAuthController;
use App\Http\Controllers\Api\resetPassVerification;
use App\Http\Controllers\Api\ValidationController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Api\UserController;
// Admin controller
use App\Http\Controllers\Admin\CSAgentPropertyAssignController;
use App\Http\Controllers\Admin\CSAgentDashboardController;
use App\Http\Controllers\Admin\PropertyAssignmentController;
use App\Http\Controllers\Admin\CsAgentController;
use App\Http\Controllers\Api\ProfileController;
// CsAgent controller
use App\Http\Controllers\CsAgent\PropertyController as CsAgentPropertyController;
use App\Http\Controllers\CsAgent\PropertyVerificationController;
use App\Http\Controllers\CsAgent\PropertyDocumentController;


Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');




// Public routes
Route::post('/register', [AuthenticationController::class, 'register']);
Route::post('/login', [AuthenticationController::class, 'login'])->middleware('throttle:5,1');

Route::post('/refresh', [AuthenticationController::class, 'refresh']);

Route::post('/verify-email', [AuthenticationController::class, 'verifyEmailOtp']);
Route::post('/resend-email-otp', [AuthenticationController::class, 'resendEmailOtp'])->middleware('throttle:2,1');

Route::post('/send-phone-otp', [AuthenticationController::class, 'sendPhoneOtp']);
Route::post('/verify-phone-otp', [AuthenticationController::class, 'verifyPhoneOtp'])->middleware('throttle:2,1');

Route::post('/upload-id', [ImageOfId::class, 'uploadIdImage']);


Route::post('/forgot-password', [forgetPasswordController::class, 'forgetPassword']);
Route::post('/reset-password', [resetPassVerification::class, 'resetPassword'])->middleware('throttle:2,1');
Route::post('/verify-reset-token', [resetPassVerification::class, 'verifyToken']);


Route::post('auth/google/exchange', [GoogleAuthController::class, 'exchangeToken']);

Route::get('auth/google/redirect', [GoogleAuthController::class, 'redirectToGoogle']);
Route::get('auth/google/callback', [GoogleAuthController::class, 'handleGoogleCallback']);

Route::get('/properties/{id}/reviews', [ReviewController::class, 'index']);
// routes/api.php
Route::get('/properties/feature-listing', [PropertyController::class, 'basicListing']);
Route::get('/properties/categories', [PropertyController::class, 'typesWithImage']);

Route::prefix('check-availability')->group(function () {

    Route::get('/email', [ValidationController::class, 'checkEmail']);

    Route::get('/phone', [ValidationController::class, 'checkPhone']);
});

Route::prefix('propertiesList')->group(function () {
    Route::get('/', [PropertyListController::class, 'index']);
    Route::get('/filters', [PropertyListController::class, 'filterOptions']);
    Route::get('/filtersOptions', [FiltersController::class, 'index']);
    Route::get('/{id}', [PropertyListController::class, 'show']);
    Route::get('/{id}', [PropertyController::class, 'showAnyone']);
});
// Protected routes
Route::middleware('auth:api')->group(function () {

    Route::post('profile', [AuthenticationController::class, 'profile']);

    Route::post('/logout', [AuthenticationController::class, 'logout']);

    Route::post('/user/change-email', [ProfileController::class, 'changeEmail']);

    Route::post('/user/change-phone', [ProfileController::class, 'changePhoneNumber']);

    Route::post('/user/change-password', [ProfileController::class, 'changePassword']);

});

Route::get('/features', [FeatureController::class, 'index']);

Route::middleware(['auth:api', 'role:owner'])->group(function () {
    //properties
    Route::apiResource('/properties', PropertyController::class);
    //owner dashboard
    Route::get('/owner/dashboard', [OwnerDashboardController::class, 'index']);
});



// Admin routes
Route::prefix('admin')->middleware(['auth:api', 'admin'])->group(function () {
    // Existing admin dashboard routes - SEM-60: Admin Dashboard API Implementation
    Route::get('/dashboard/stats', [DashboardController::class, 'getStats']);
    Route::get('/dashboard/charts/revenue', [DashboardController::class, 'getRevenueChart']);
    Route::get('/dashboard/charts/users', [DashboardController::class, 'getUsersChart']);
    Route::get('/dashboard/charts/properties', [DashboardController::class, 'getPropertiesChart']);

    // SEM-61: Simple Admin Users Management Routes
    Route::prefix('users')->group(function () {
        // Search and statistics (must come before parameterized routes)
        Route::get('/search', [App\Http\Controllers\Admin\UserController::class, 'search']);
        Route::get('/statistics', [App\Http\Controllers\Admin\UserController::class, 'statistics']);
        Route::get('/requires-attention', [App\Http\Controllers\Admin\UserController::class, 'requiresAttention']);

        // View users
        Route::get('/', [App\Http\Controllers\Admin\UserController::class, 'index']);
        Route::get('/{id}', [App\Http\Controllers\Admin\UserController::class, 'show']);

        // User status management
        Route::post('/{id}/activate', [App\Http\Controllers\Admin\UserController::class, 'activate']);
        Route::post('/{id}/suspend', [App\Http\Controllers\Admin\UserController::class, 'suspend']);
        Route::post('/{id}/block', [App\Http\Controllers\Admin\UserController::class, 'block']);

        // User activity
        Route::get('/{id}/activity', [App\Http\Controllers\Admin\UserController::class, 'getUserActivity']);
    });

    // SEM-62: Admin Properties Management Routes
    Route::prefix('properties')->group(function () {
        // Search and statistics MUST come first (before /{id})
        Route::get('/search', [App\Http\Controllers\Admin\PropertyController::class, 'search']);
        Route::get('/statistics', [App\Http\Controllers\Admin\PropertyController::class, 'getStatistics']);
        Route::get('/requires-attention', [App\Http\Controllers\Admin\PropertyController::class, 'requiresAttention']);

        // Bulk operations
        Route::post('/bulk/approve', [App\Http\Controllers\Admin\PropertyController::class, 'bulkApprove']);
        Route::post('/bulk/reject', [App\Http\Controllers\Admin\PropertyController::class, 'bulkReject']);

        // Individual property operations
        Route::get('/', [App\Http\Controllers\Admin\PropertyController::class, 'index']);
        Route::get('/{id}', [App\Http\Controllers\Admin\PropertyController::class, 'show']);
        Route::post('/{id}/status', [App\Http\Controllers\Admin\PropertyController::class, 'updateStatus']);
        Route::delete('/{id}', [App\Http\Controllers\Admin\PropertyController::class, 'destroy']);

        // SEM-65 Property Assignment to CS Agent
        Route::post('/{property}/assign-cs-agent', [PropertyAssignmentController::class, 'store']);
    });

    // SEM-65 CS Agent Management Routes
    Route::get('/cs-agents', [CsAgentController::class, 'index']);

     // SEM-64: CS Agent Dashboard API Implementation
     Route::prefix('cs-agents')->group(function () {
        // Dashboard overview (must come before parameterized routes)
        Route::get('/dashboard', [CSAgentDashboardController::class, 'getDashboardData']);
        Route::get('/dashboard/charts/assignments', [CSAgentDashboardController::class, 'getAssignmentsChart']);
        Route::get('/dashboard/charts/performance', [CSAgentDashboardController::class, 'getAgentPerformanceChart']);
        Route::get('/dashboard/charts/workload', [CSAgentDashboardController::class, 'getWorkloadChart']);
        Route::get('/dashboard/attention', [CSAgentDashboardController::class, 'getAssignmentsRequiringAttention']);

        // Assignment management
        Route::prefix('assignments')->group(function () {
            // Statistics and utilities (must come before parameterized routes)
            Route::get('/statistics', [CSAgentPropertyAssignController::class, 'getStatistics']);
            Route::get('/available-agents', [CSAgentPropertyAssignController::class, 'getAvailableAgents']);

            // Bulk operations
            Route::post('/bulk-assign', [CSAgentPropertyAssignController::class, 'bulkAssign']);

            // CRUD operations
            Route::get('/', [CSAgentPropertyAssignController::class, 'index']);
            Route::post('/', [CSAgentPropertyAssignController::class, 'store']);
            Route::get('/{id}', [CSAgentPropertyAssignController::class, 'show']);
            Route::put('/{id}', [CSAgentPropertyAssignController::class, 'update']);
            Route::delete('/{id}', [CSAgentPropertyAssignController::class, 'destroy']);

            // Special operations
            Route::post('/{id}/reassign', [CSAgentPropertyAssignController::class, 'reassign']);
        });
    });
});

// SEM-65 CS Agent routes (for agents to manage their own assignments)
Route::prefix('cs-agent')->middleware(['auth:api', 'role:agent'])->group(function () {
    // Get assigned properties (task queue)
    Route::get('/properties', [CsAgentPropertyController::class, 'index']);

    // Update verification status
    Route::patch('/properties/{property}/status', [PropertyVerificationController::class, 'update']);

    // Upload verification documents
    Route::post('/properties/{property}/documents', [PropertyDocumentController::class, 'store']);

    // Agent's dashboard (simplified version)
    Route::get('/dashboard', function (Request $request) {
        $agent = $request->user();

        if (!$agent->isCsAgent()) {
            return response()->json([
                'success' => false,
                'message' => 'Access denied. User is not a CS Agent.'
            ], 403);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'agent' => [
                    'id' => $agent->id,
                    'name' => $agent->getFullNameAttribute(),
                    'email' => $agent->email,
                ],
                'assignments' => [
                    'active' => $agent->getActiveAssignmentsCount(),
                    'completed' => $agent->getCompletedAssignmentsCount(),
                    'average_completion_time' => $agent->getAverageCompletionTime(),
                ],
                'recent_assignments' => $agent->getCurrentAssignments()->limit(5)->get(),
            ]
        ]);
    });
});

Route::prefix('user/{id}')->group(function ($id) {
    Route::get('/', [UserController::class, 'index']);
    Route::get('/reviews', [UserController::class, 'reviews']);
    Route::get('/properties', [UserController::class, 'properties']);
    Route::get('/notifications', [UserController::class, 'notifications']);
    Route::get('/purchases', [UserController::class, 'purchases']);
    Route::get('/bookings', [UserController::class, 'bookings']);
    Route::get('/wishlists', [UserController::class, 'wishlists']);
    Route::patch('/notifications/{notificationid}/read', [UserController::class, 'markAsRead']);
});

Route::middleware('auth:api')->group(function () {
    Route::get('/wishlist', [WishlistController::class, 'index']);
    Route::post('/wishlist', [WishlistController::class, 'store']);
    Route::delete('/wishlist/{propertyId}', [WishlistController::class, 'destroy']);
});

