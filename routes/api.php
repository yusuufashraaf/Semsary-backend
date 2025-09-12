<?php
use App\Http\Controllers\OwnerDashboardController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\PropertyController;
use App\Http\Controllers\Api\AuthenticationController;


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

// Protected routes
Route::middleware('auth:api')->group(function () {
    Route::post('profile', [AuthenticationController::class, 'profile']);
    Route::post('/logout', [AuthenticationController::class, 'logout']);



});

Route::middleware(['auth:api', 'role:owner'])->group(function () {
    //properties
    Route::apiResource('properties', PropertyController::class);
    //owner dashboard
    Route::get('/owner/dashboard', [OwnerDashboardController::class, 'index']);
});

