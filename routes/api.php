<?php
use App\Http\Controllers\Api\FiltersController;
use App\Http\Controllers\Api\PropertyListController;
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
use App\Http\Controllers\Admin\DashboardController;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');




// Public routes
Route::post('/register', [AuthenticationController::class, 'register']);
Route::post('/login', [AuthenticationController::class, 'login']);

Route::post('/refresh', [AuthenticationController::class, 'refresh']);

Route::post('/verify-email', [AuthenticationController::class, 'verifyEmailOtp']);
Route::post('/resend-email-otp', [AuthenticationController::class, 'resendEmailOtp']);

Route::post('/send-phone-otp', [AuthenticationController::class, 'sendPhoneOtp']);
Route::post('/verify-phone-otp', [AuthenticationController::class, 'verifyPhoneOtp']);

Route::post('/upload-id', [ImageOfId::class, 'uploadIdImage']);


Route::post('/forgot-password', [forgetPasswordController::class, 'forgetPassword']);
Route::post('/reset-password', [resetPassVerification::class, 'resetPassword']);
Route::post('/verify-reset-token', [resetPassVerification::class, 'verifyToken']);


 Route::post('auth/google/exchange', [GoogleAuthController::class, 'exchangeToken']);

Route::get('auth/google/redirect', [GoogleAuthController::class, 'redirectToGoogle']);
Route::get('auth/google/callback', [GoogleAuthController::class, 'handleGoogleCallback']);


// Protected routes
Route::middleware('auth:api')->group(function () {
    Route::post('profile', [AuthenticationController::class, 'profile']);
    Route::post('/logout', [AuthenticationController::class, 'logout']);



});

Route::get('/features', [FeatureController::class, 'index']);

Route::middleware(['auth:api', 'role:owner'])->group(function () {
    //properties
    Route::apiResource('/properties', PropertyController::class);
    //owner dashboard
    Route::get('/owner/dashboard', [OwnerDashboardController::class, 'index']);
});

Route::prefix('propertiesList')->group(function () {
    Route::get('/', [PropertyListController::class, 'index']);
    Route::get('/filters', [PropertyListController::class, 'filterOptions']);
    Route::get('/filtersOptions', [FiltersController::class, 'index']);
    Route::get('/{id}', [PropertyController::class, 'showAnyone']);
});

Route::get('/properties/{id}/reviews', [ReviewController::class, 'index']);

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
    });
});
