<?php
use App\Http\Controllers\Api\FiltersController;
use App\Http\Controllers\Api\PropertyListController;
use App\Http\Controllers\Api\WishlistController;
use App\Http\Controllers\FeatureController;
use App\Http\Controllers\ChatbotController;
use App\Http\Controllers\OwnerDashboardController;
use App\Http\Controllers\PropertyDetailsController;
use App\Http\Controllers\RentRequestController;
use App\Http\Controllers\ReviewAnalysisController;
use App\Http\Controllers\ReviewController;
use App\Models\Review;
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
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\ProfileController;
// CsAgent controller
use App\Http\Controllers\CsAgent\PropertyController as CsAgentPropertyController;
use App\Http\Controllers\CsAgent\PropertyVerificationController;
use App\Http\Controllers\CsAgent\PropertyDocumentController;
use App\Http\Controllers\MessageController;

use App\Http\Controllers\Api\CheckoutController;
use App\Http\Controllers\UserNotificationController;
use App\Http\Controllers\UserBalanceController;

// Withdraw
use App\Http\Controllers\WithdrawalController;

// Buy Property
use App\Http\Controllers\PropertyPurchaseController;

use App\Http\Controllers\NewMessageController;

use App\Http\Controllers\Admin\AdminChatController;


//wallet
use App\Http\Controllers\Api\BalanceApiController;

use App\Http\Controllers\PaymobCallbackController;

Route::get('/user', function (Request $request) {
    return true;//$request->user();
})->middleware('auth:sanctum');

//Realtime Messaging
Route::post('/send-message',[NewMessageController::class,'sendMessage']);
Route::post('/broadcasting/auth',[NewMessageController::class,'authenticateBroadcast']);
Route::get('/fetch-messages/{chatId}',[NewMessageController::class,'fetchMessages']);
Route::get('/fetch-chats/{userId}',[NewMessageController::class,'fetchChats']);
Route::get('/fetch-available-chats/{userId}',[NewMessageController::class,'fetchAvailableChats']);


// Public routes
Route::post('/register', [AuthenticationController::class, 'register']);
Route::post('/login', [AuthenticationController::class, 'login'])->middleware('throttle:5,1');

Route::post('/refresh', [AuthenticationController::class, 'refresh']);

Route::post('/verify-email', [AuthenticationController::class, 'verifyEmailOtp']);
Route::post('/resend-email-otp', [AuthenticationController::class, 'resendEmailOtp'])->middleware('throttle:4,1');

Route::post('/send-phone-otp', [AuthenticationController::class, 'sendPhoneOtp']);
Route::post('/verify-phone-otp', [AuthenticationController::class, 'verifyPhoneOtp'])->middleware('throttle:4,1');

Route::post('/upload-id', [ImageOfId::class, 'uploadIdImage']);


Route::post('/forgot-password', [forgetPasswordController::class, 'forgetPassword']);
Route::post('/reset-password', [resetPassVerification::class, 'resetPassword'])->middleware('throttle:4,1');
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

// Property Purchase routes

Route::middleware(['auth:api', 'purchase.limit'])->group(function () {
    Route::post('/properties/{id}/purchase', [PropertyPurchaseController::class, 'payForOwn']);
        Route::post('/purchases/{id}/cancel', [PropertyPurchaseController::class, 'cancelPurchase']);
});

Route::middleware('auth:api')->group(function () {
    Route::get('/purchases/cancellable', [PropertyPurchaseController::class, 'getUserCancellablePurchases']);
    Route::get('/purchases', [PropertyPurchaseController::class, 'getAllPurchases']);
    Route::get('/user/transactions', [PropertyPurchaseController::class, 'getAllUserTransactions']);
});


  // payment
Route::get('/exchange-payment-token', [PaymentController::class, 'exchangePaymentToken']);

Route::middleware('auth:api')->group(function () {

    Route::post('profile', [AuthenticationController::class, 'profile']);

    Route::post('/logout', [AuthenticationController::class, 'logout']);

    Route::post('/user/change-email', [ProfileController::class, 'changeEmail']);

    Route::post('/user/change-phone', [ProfileController::class, 'changePhoneNumber']);

    Route::post('/user/change-password', [ProfileController::class, 'changePassword']);

    Route::post('/payment/process', [PaymentController::class, 'paymentProcess']);

});

Route::get('/features', [FeatureController::class, 'index']);

Route::middleware(['auth:api'])->group(function () {
    //properties
    Route::apiResource('/properties', PropertyController::class);
    //Route::get('/properties', PropertyController::class);
        //owner dashboard
    Route::get('/owner/dashboard', [OwnerDashboardController::class, 'index']);
    Route::get('/user/dashboard-stats', [OwnerDashboardController::class, 'stats']);

});


Route::put('/admin/users/{id}/status/{status}', [App\Http\Controllers\Admin\UserController::class, 'updateState'])->middleware(['auth:api']);
Route::put('/admin/users/{id}/id_state/{status}', [App\Http\Controllers\Admin\UserController::class, 'updateIdState'])->middleware(['auth:api']);
Route::put('/admin/users/{id}/role/{status}', [App\Http\Controllers\Admin\UserController::class, 'updateRole'])->middleware(['auth:api']);
Route::put('/admin/users/{id}/delete', [App\Http\Controllers\Admin\UserController::class, 'deleteUser'])->middleware(['auth:api']);
Route::post('/admin/users/{id}/notify', [App\Http\Controllers\Admin\UserController::class, 'notifyUser'])->middleware(['auth:api']);
Route::get('/admin/users/{id}/role/{status}', [App\Http\Controllers\Admin\UserController::class, 'updateRole']);
Route::get('/admin/users/{id}/verifyAdmin', [App\Http\Controllers\Admin\UserController::class, 'verifyAdmin']);
// Admin routes
Route::prefix('admin')->middleware(['auth:api'])->group(function () {
    // Existing admin dashboard routes - SEM-60: Admin Dashboard API Implementation
    Route::get('/dashboard/stats', [DashboardController::class, 'getStats']);
    Route::get('/dashboard/charts/revenue', [DashboardController::class, 'getRevenueChart']);
    Route::get('/dashboard/charts/users', [DashboardController::class, 'getUsersChart']);
    Route::get('/dashboard/charts/properties', [DashboardController::class, 'getPropertiesChart']);

    // SEM-61: Simple Admin Users Management Routes
    Route::prefix('/users')->group(function () {
        // Search and statistics (must come before parameterized routes)
        Route::get('/search', [App\Http\Controllers\Admin\UserController::class, 'search']);
        Route::get('/statistics', [App\Http\Controllers\Admin\UserController::class, 'statistics']);
        Route::get('/requires-attention', [App\Http\Controllers\Admin\UserController::class, 'requiresAttention']);

        // View users
        Route::get('/', [App\Http\Controllers\Admin\UserController::class, 'index']);


        // User status management
        Route::put('/{id}/status/{status}', [App\Http\Controllers\Admin\UserController::class, 'updateState']);
        Route::post('/{id}/activate', [App\Http\Controllers\Admin\UserController::class, 'activate']);
        Route::post('/{id}/suspend', [App\Http\Controllers\Admin\UserController::class, 'suspend']);
        Route::post('/{id}/block', [App\Http\Controllers\Admin\UserController::class, 'block']);

        // User activity
        Route::get('/{id}/activity', [App\Http\Controllers\Admin\UserController::class, 'getUserActivity']);
        Route::get('/{id}', [App\Http\Controllers\Admin\UserController::class, 'show']);
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
    Route::get('/cs-agents/{id}', [CsAgentController::class, 'show']);
    Route::get('/cs-agents/{id}/assignments', [CsAgentController::class, 'getAssignments']);

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

Route::middleware(['auth:api', 'role:admin,agent'])->group(function () {
    Route::get('/admin/users/{id}', [App\Http\Controllers\Admin\UserController::class, 'show']);
});

// SEM-65 CS Agent routes (for agents to manage their own assignments)
Route::prefix('cs-agent')->middleware(['auth:api', 'role:agent'])->group(function () {
    // Get assigned properties (task queue)
    Route::get('/properties', [CsAgentPropertyController::class, 'index']);

    // Get detailed view of a specific assigned property
    Route::get('/properties/{id}', [CsAgentPropertyController::class, 'show']);

    // Get timeline/history for a specific assigned property
    Route::get('/properties/{property}/timeline', [CsAgentPropertyController::class, 'getTimeline']);

    // Add note to property timeline
    Route::post('/properties/{property}/notes', [CsAgentPropertyController::class, 'addNote']);

    // Update verification status
    Route::patch('/properties/{property}/status', [PropertyVerificationController::class, 'update']);

    Route::patch('/properties/{property}/state', [CsAgentPropertyController::class, 'updateState']);

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


Route::middleware('auth:api')->prefix('user')->group(function () {

    Route::get('/chats', [MessageController::class, 'getUserChats']);

    Route::get('/chats/{chat}/messages', [MessageController::class, 'getChatMessages']);

    Route::post('/chats/{chat}/messages', [MessageController::class, 'sendMessage']);

    Route::post('/chats/start', [MessageController::class, 'startChat']);

    Route::post('/chats/{chat}/read', [MessageController::class, 'markAsRead']);

    // ADD THIS ROUTE for broadcasting authentication
    Route::post('/broadcasting/auth', [MessageController::class, 'authenticateBroadcast']);
});

Route::middleware('auth:api')->get('user/reviewable-properties', [ReviewController::class, 'getReviewableProperties']);

Route::middleware('auth:api')->prefix('user/{id}')->group(function ($id) {
    Route::get('/', [UserController::class, 'index']);
    Route::get('/reviews', [UserController::class, 'reviews']);
    Route::get('/properties', [UserController::class, 'properties']);
    Route::get('/notifications', [UserController::class, 'notifications']);
    // Route::get('/notificationread/{notificationId}', [UserController::class, 'markAsRead']);
    Route::get('/purchases', [UserController::class, 'purchases']);
    Route::get('/bookings', [UserController::class, 'bookings']);
    Route::get('/wishlists', [UserController::class, 'wishlists']);
    Route::patch('/notifications/{notificationid}/read', [UserController::class, 'markAsRead']);
});



Route::middleware('auth:api')->group(function () {
    Route::post('/reviews', [ReviewController::class, 'store']);
    Route::put('/reviews/{review}', [ReviewController::class, 'update']);
    Route::delete('/reviews/{review}', [ReviewController::class, 'destroy']);
    Route::get('/properties/{property}/reviews', [ReviewController::class, 'getPropertyReviews']);
    Route::get('/user/reviewable-properties', [ReviewController::class, 'getReviewableProperties']);
    Route::get('/users/{user}/reviews', [ReviewController::class, 'getUserReviews']);

});




Route::middleware('auth:api')->group(function () {
    Route::get('/wishlist', [WishlistController::class, 'index']);
    Route::post('/wishlist', [WishlistController::class, 'store']);
    Route::delete('/wishlist/{propertyId}', [WishlistController::class, 'destroy']);
});


Route::middleware('auth:api')->group(function () {
    // Rent request creation
    Route::post('/rent-requests', [RentRequestController::class, 'createRequest']);

    // Cancel by user
    Route::post('/rent-requests/{id}/cancel', [RentRequestController::class, 'cancelRequestByUser']);

    // Owner actions
    Route::post('/rent-requests/{id}/confirm', [RentRequestController::class, 'confirmRequestByOwner']);
    Route::post('/rent-requests/{id}/reject', [RentRequestController::class, 'rejectRequestByOwner']);
    Route::post('/rent-requests/{id}/cancel-by-owner', [RentRequestController::class, 'cancelConfirmedByOwner']);

    // Payment
    Route::post('/rent-requests/{id}/pay', [RentRequestController::class, 'payForRequest']);

    // Listings
    Route::get('/rent-requests/user', [RentRequestController::class, 'listUserRequests']);
    Route::get('/rent-requests/owner', [RentRequestController::class, 'listOwnerRequests']);

    // Additional endpoints
    Route::get('/rent-requests/stats', [RentRequestController::class, 'getRequestStats']);
    Route::get('/rent-requests/{id}', [RentRequestController::class, 'getRequestDetails']);
});




    // Get all chats for authenticated user
    Route::middleware('auth:sanctum')->group(function () {

});




// System (cron job or scheduler) — protected via console command or internal token
Route::post('/rent-requests/auto-cancel', [RentRequestController::class, 'autoCancelUnpaidRequests']);
// AI Routes
Route::post('/chatbot', [ChatbotController::class, 'handleChat']);
Route::post('/properties/generate-description', [PropertyController::class, 'generateDescription']);
//openrouterai
Route::get('/properties/{property}/reviews/analysis', [ReviewAnalysisController::class, 'analyze']);

// Checkout routes
Route::middleware('auth:api')->group(function () {
    // === User Actions ===
    Route::post('/checkout/{rentRequestId}', [CheckoutController::class, 'processCheckout']);
    Route::get('/checkout/{rentRequestId}', [CheckoutController::class, 'getCheckoutStatus']);

    // === Owner Actions ===
    Route::post('/checkout/{checkoutId}/owner/confirm', [CheckoutController::class, 'handleOwnerConfirm']);
    Route::post('/checkout/{checkoutId}/owner/reject', [CheckoutController::class, 'handleOwnerReject']);

    // === Agent Actions ===
    Route::post('/checkout/{checkoutId}/agent-decision', [CheckoutController::class, 'handleAgentDecision']);

    // === Query Checkouts ===
    Route::get('/checkouts/stats', [CheckoutController::class, 'getCheckoutStats']);
    Route::get('/checkouts/{checkoutId}', [CheckoutController::class, 'getCheckoutDetails']);
    Route::get('/checkouts/user', [CheckoutController::class, 'listUserCheckouts']);
    Route::get('/checkouts/admin', [CheckoutController::class, 'listAdminCheckouts']);

    // === Transactions ===
    Route::get('/transactions', [CheckoutController::class, 'listTransactions']);
});

// === System Cron (Admin only) ===
Route::middleware(['auth:api', 'role:admin'])->group(function () {
    Route::post('/system/auto-confirm-checkouts', [CheckoutController::class, 'autoConfirmExpiredCheckouts']);
});

// User Notification
Route::middleware('auth:api')->get('/notifications', [UserNotificationController::class, 'getUserNotifications']);

// User Balance
Route::middleware('auth:api')->get('/balances', [UserBalanceController::class, 'getBalances']);

// Withdraw

Route::middleware(['auth:api'])->group(function () {
    // Withdrawal routes
    Route::prefix('withdrawals')->group(function () {
        Route::get('/info', [WithdrawalController::class, 'getWithdrawalInfo']);
        Route::post('/request', [WithdrawalController::class, 'requestWithdrawal']);
        Route::get('/history', [WithdrawalController::class, 'getWithdrawalHistory']);
    });
});


Route::get('/properties/{id}/unavailable-dates', [RentRequestController::class, 'getUnavailableDates']);


// Property Purchase routes (add these to your existing routes)
Route::middleware('auth:api')->group(function () {

    // NEW: Get user's purchases only
    Route::get('/user/purchases', [PropertyPurchaseController::class, 'getUserPurchases']);

    // NEW: Get user's purchase for specific property
    Route::get('/properties/{propertyId}/purchase', [PropertyPurchaseController::class, 'getUserPurchaseForProperty']);

});

// -------------------- WALLET TOP UP --------------------
Route::middleware('auth:api')->group(function () {
    // Start a wallet top-up (returns payment_key + iframe url)
    Route::post('/wallet/topup', [PaymentController::class, 'topUpWallet']);

    // After callback, frontend exchanges temp token for final payment status
    Route::post('/wallet/exchange-token', [PaymentController::class, 'exchangePaymentToken']);
});


    // Wallet
    Route::middleware('auth:api')->get('/balance', [BalanceApiController::class, 'show']);

Route::prefix('admin')->middleware(['auth:api', 'admin'])->group(function () {
    Route::prefix('users')->group(function () {
        Route::post('/{id}/change-role', [\App\Http\Controllers\Admin\UserController::class, 'changeRole']);
    });
});


// Route::get('/properties', [PropertyController::class, 'getProperties']);
Route::put('/properties/{id}/change-status', [PropertyController::class, 'changeStatus'])->name('properties.changeStatus');

Route::middleware(['auth:api', 'role:admin'])
    ->prefix('admin/chats')
    ->group(function () {
        Route::get('/', [AdminChatController::class, 'index']);       // List chats + agents
        Route::get('/{id}', [AdminChatController::class, 'show']);    // Single chat details
        Route::post('/{id}/assign', [AdminChatController::class, 'assign']);   // Assign agent
        Route::post('/{id}/unassign', [AdminChatController::class, 'unassign']); // Unassign agent
        Route::get('/agents', [AdminChatController::class, 'agents']); // Just list agents
    });

Route::match(['get', 'post'], '/payment/paymob/callback', [PaymentController::class, 'handle']);
