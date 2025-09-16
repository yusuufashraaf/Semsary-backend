<?php
use App\Http\Controllers\FeatureController;
use App\Http\Controllers\OwnerDashboardController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\PropertyController;
use App\Http\Controllers\Api\AuthenticationController;
use App\Http\Controllers\Api\ImageOfId;
use App\Http\Controllers\Api\forgetPasswordController;
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


Route::post('/forgot-password', [forgetPasswordController::class,'forgetPassword']);
Route::post('/reset-password', [resetPassVerification::class,'resetPassword']);
Route::post('/verify-reset-token', [resetPassVerification::class, 'verifyToken']);

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


// Admin routes
Route::prefix('admin')->middleware(['auth:api', 'admin'])->group(function () {
    // Admin Dashboard Statistics - SEM-60: Admin Dashboard API Implementation
    Route::get('/dashboard/stats', [DashboardController::class, 'getStats']);
    Route::get('/dashboard/charts/revenue', [DashboardController::class, 'getRevenueChart']);
    Route::get('/dashboard/charts/users', [DashboardController::class, 'getUsersChart']);
    Route::get('/dashboard/charts/properties', [DashboardController::class, 'getPropertiesChart']);
});
