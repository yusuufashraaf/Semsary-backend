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
use App\Http\Controllers\Api\UserController;

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
    Route::get('/{id}', [PropertyListController::class, 'show']);
    Route::get('/{id}', [PropertyController::class, 'showAnyone']);
});

Route::get('/properties/{id}/reviews', [ReviewController::class, 'index']);

Route::prefix('user/{id}')->group(function ($id) {
    Route::get('/', [UserController::class, 'index']);
    Route::get('/reviews', [UserController::class, 'reviews']);
    Route::get('/properties', [UserController::class, 'properties']);
    Route::get('/notifications', [UserController::class, 'notifications']);
    Route::get('/purchases', [UserController::class, 'purchases']);
    Route::get('/bookings', [UserController::class, 'bookings']);
});
    

