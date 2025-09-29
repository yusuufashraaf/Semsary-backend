<?php

namespace App\Http\Controllers;

use App\Events\RequestAutoCancelledEvent;
use App\Models\Purchase;
use App\Models\RentRequest;
use App\Models\Property;
use App\Models\UserNotification;
use App\Enums\NotificationPurpose;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Carbon\Carbon;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use App\Models\EscrowBalance;

use Exception;

class RentRequestController extends Controller
{
    // Helper uniform responses
    protected function success($message, $data = null, $status = 200)
    {
        $payload = ['success' => true, 'message' => $message];
        if (!is_null($data))
            $payload['data'] = $data;
        return response()->json($payload, $status);
    }

    protected function error($message, $status = 422, $details = null)
    {
        $payload = [
            'success' => false, // Changed for consistency
            'message' => $message,
        ];

        if (!is_null($details)) {
            $payload['errors'] = $details;
        }

        return response()->json($payload, $status);
    }

    /**
     * Helper to create UserNotification from websocket notification data
     */
    private function createUserNotificationFromWebsocketData(
        $recipient,
        $notificationClass,
        NotificationPurpose $purpose,
        $senderId = null
    ) {
        try {
            // Get notification data (same as websocket)
            $notificationData = null;
            if (method_exists($notificationClass, 'toDatabase')) {
                $notificationData = $notificationClass->toDatabase($recipient);
            } elseif (method_exists($notificationClass, 'toBroadcast')) {
                $broadcastData = $notificationClass->toBroadcast($recipient);
                $notificationData = $broadcastData->data ?? $broadcastData;
            }

            // Extract data same as websocket structure
            $entityId = $notificationData['rent_request_id'] ?? $notificationData['id'] ?? $notificationData['property_id'] ?? null;
            $message = $notificationData['message'] ?? 'New notification';
Log::info('Inserting notification', [
    'recipient' => $recipient->id,
    'purpose'   => $purpose->value,
]);
            // Create UserNotification record
            $notification=UserNotification::create([
                'user_id' => $recipient->id,
                'sender_id' => $senderId,
                'entity_id' => $entityId,
                'purpose' => $purpose->value,
                'title' => $purpose->label(),
                'message' => $message,
                'is_read' => false,
            ]);
Log::info('Notification row created', [
    'id' => optional($notification)->id
]);
        } catch (Exception $e) {
            Log::warning('Failed to create UserNotification', [
                'error' => $e->getMessage(),
                'recipient_id' => $recipient->id,
            ]);
        }
    }

    /**
     * Create a new rent request
     */
public function createRequest(Request $request)
{
    // Minimal validation - only the essentials
    $validator = \Validator::make($request->all(), [
        'property_id' => 'required|integer|exists:properties,id',
        'check_in' => 'required|date|after:today',
        'check_out' => 'required|date|after:check_in',
    ]);

    if ($validator->fails()) {
        return $this->error('check in must be a day after today and check out must be after check in.', 422, $validator->errors());
    }

    $user = $request->user();
    if (!$user) {
        return $this->error('Authentication required.', 401);
    }

    // Basic business validations
    if ($user->status != "active") {
        return $this->error('Your account must be verified before making requests.', 403);
    }

    // Rate limiting - max 5 requests per hour per user
    $recentRequests = RentRequest::where('user_id', $user->id)
        ->where('created_at', '>', Carbon::now()->subHour())
        ->count();

    if ($recentRequests >= 20) {
        return $this->error('Too many requests. Please wait before making another request.', 429);
    }

    // Parse the check-in and check-out dates
    $checkIn = Carbon::parse($request->input('check_in'))->startOfDay();
    $checkOut = Carbon::parse($request->input('check_out'))->startOfDay();

    // Check that the check-in date is in the future
    if ($checkIn->lt(Carbon::today())) {
        return $this->error('Check-in date must be in the future.', 422);
    }

    // Check that the check-out date is after the check-in date
    if ($checkOut->lte($checkIn)) {
        return $this->error('Check-out must be after check-in.', 422);
    }

    // Start transaction to handle potential issues with race conditions
    try {
        return DB::transaction(function () use ($request, $user, $checkIn, $checkOut) {
            // Lock property row to prevent concurrent bookings
            $property = Property::lockForUpdate()->find($request->property_id);

            if (!$property) {
                return $this->error('Property not found.', 404);
            }

            // Prevent owner from requesting their own property
            if ($property->owner_id === $user->id) {
                return $this->error('You cannot make a request for your own property.', 422);
            }

            // Enhanced property status check
            if (!in_array($property->property_state, ['Valid', 'Available'])) {
                return $this->error('Property is not available for rent.', 422);
            }

            // Check if property owner account is active
            if ($property->owner->status !== 'active') {
                return $this->error('Property owner account is not active.', 422);
            }

            // Check if the user is temporarily blocked from requesting this property
            $blocked = RentRequest::where('property_id', $property->id)
                ->where('user_id', $user->id)
                ->whereNotNull('blocked_until')
                ->where('blocked_until', '>', Carbon::now())
                ->exists();

            if ($blocked) {
                return $this->error('You are temporarily blocked from requesting this property. Try later.', 403);
            }

            // Check for cooldown after a cancellation/rejection
            $cooldown = RentRequest::where('property_id', $property->id)
                ->where('user_id', $user->id)
                ->whereNotNull('cooldown_expires_at')
                ->where('cooldown_expires_at', '>', Carbon::now())
                ->exists();

            if ($cooldown) {
                return $this->error('You are in cooldown for this property due to a previous cancellation. Try later.', 403);
            }

            // Calculate everything server-side from property data
            $nights = $checkIn->diffInDays($checkOut);
            $pricePerNight = $property->price_per_night; // Get current price from database
            $totalPrice = $pricePerNight * $nights;

            // Check for date overlap with proper locking
            $overlap = RentRequest::where('property_id', $property->id)
                ->whereIn('status', ['pending', 'confirmed', 'paid'])
                ->lockForUpdate()
                ->where(function ($q) use ($checkIn, $checkOut) {
                    $q->where(function ($s) use ($checkIn, $checkOut) {
                        $s->where('check_in', '<', $checkOut)
                            ->where('check_out', '>', $checkIn);
                    });
                })
                ->exists();

            if ($overlap) {
                return $this->error('Property already has an active request for the selected dates.', 422);
            }

            // Create the rent request with calculated values
            $rentRequest = RentRequest::create([
                'user_id' => $user->id,
                'property_id' => $property->id,
                'check_in' => $checkIn->toDateString(),
                'check_out' => $checkOut->toDateString(),
                'guests' => $property->default_guests ?? 2,
                'total_price' => $totalPrice,
                'nights' => $nights,
                'price_per_night' => $pricePerNight,
                'message' => null, // No message needed
                'status' => 'pending',
            ]);

            // Log the action for audit trail
            Log::info('Rent request created', [
                'user_id' => $user->id,
                'property_id' => $property->id,
                'request_id' => $rentRequest->id,
                'dates' => [$checkIn->toDateString(), $checkOut->toDateString()],
                'nights' => $nights,
                'total_price' => $totalPrice,
            ]);

            // FIXED: Safe notification with error handling
            try {
                $notification = new \App\Notifications\RentRequested($rentRequest);
                Notification::send($property->owner, $notification);
                
                // Create UserNotification from websocket data
                $this->createUserNotificationFromWebsocketData(
                    $property->owner,
                    $notification,
                    NotificationPurpose::RENT_REQUESTED,
                    $user->id
                );
            } catch (Exception $e) {
                Log::warning('Failed to send rent request notification', [
                    'error' => $e->getMessage(),
                    'request_id' => $rentRequest->id
                ]);
                // Don't fail the entire request for notification issues
            }

            return $this->success('Rent request created successfully.', $rentRequest, 201);
        });
    } catch (Exception $e) {
        // Log error and return failure response
        Log::error('createRequest error: ' . $e->getMessage(), [
            'trace' => $e->getTraceAsString(),
            'user_id' => $user->id ?? null,
            'property_id' => $request->property_id ?? null,
        ]);
        return $this->error('Failed to create request. Try again later.', 500);
    }
}
    /**
     * Cancel request by user
     */
    public function cancelRequestByUser(Request $request, $id)
    {
        try {
            $user = $request->user();
            $rentRequest = RentRequest::where('id', $id)->firstOrFail();

            if ($rentRequest->user_id !== $user->id) {
                return $this->error('You are not allowed to cancel this request.', 403);
            }

            // Disallow cancel after payment; must use checkout flow
            if ($rentRequest->status === 'paid') {
                return $this->error('Cannot cancel after payment. Please request checkout.', 422);
            }

            // If already finalised
            if (in_array($rentRequest->status, ['cancelled', 'rejected', 'cancelled_by_owner', 'completed'])) {
                return $this->error('This request cannot be cancelled.', 422);
            }

            // Handle cooldown for cancel after owner confirm
            if ($rentRequest->status === 'confirmed') {
                $rentRequest->status = 'cancelled';
                $rentRequest->cooldown_expires_at = Carbon::now()->addMinute(); // business rule
            } else {
                $rentRequest->status = 'cancelled';
            }

            $rentRequest->save();

            // Log the action
            Log::info('Rent request cancelled by user', [
                'user_id' => $user->id,
                'request_id' => $rentRequest->id,
                'previous_status' => $rentRequest->getOriginal('status'),
            ]);

            // FIXED: Safe notification with error handling
            try {
                $notification = new \App\Notifications\UserRejectsRequest($rentRequest);
                Notification::send($rentRequest->property->owner, $notification);
                
                // Create UserNotification from websocket data
                $this->createUserNotificationFromWebsocketData(
                    $rentRequest->property->owner,
                    $notification,
                    NotificationPurpose::USER_REJECTS_REQUEST,
                    $user->id
                );
            } catch (Exception $e) {
                Log::warning('Failed to send cancellation notification', [
                    'error' => $e->getMessage(),
                    'request_id' => $rentRequest->id
                ]);
            }

            return $this->success('Request cancelled successfully.');
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return $this->error('Request not found.', 404);
        } catch (Exception $e) {
            Log::error('cancelRequestByUser error: ' . $e->getMessage(), [
                'request_id' => $id,
                'user_id' => $request->user()->id ?? null,
            ]);
            return $this->error('Failed to cancel request. Try again later.', 500);
        }
    }

    /**
     * Owner confirms request
     */
    public function confirmRequestByOwner(Request $request, $id)
    {
        $owner = $request->user();
        try {
            return DB::transaction(function () use ($id, $owner) {
                $rentRequest = RentRequest::lockForUpdate()->findOrFail($id);

                // Validate request status
                if ($rentRequest->status !== 'pending') {
                    return $this->error('Only pending requests can be confirmed.', 422);
                }

                // Check property ownership
                $property = $rentRequest->property()->lockForUpdate()->first();
                if (!$property) {
                    return $this->error('Property not found.', 404);
                }

                if ($property->owner_id !== $owner->id) {
                    return $this->error('You are not the owner of this property.', 403);
                }

                // Check if owner account is active
                if ($owner->status !== 'active') {
                    return $this->error('Your account must be active to confirm requests.', 403);
                }

                // Check if property is still available and not already booked
                // If a paid request was created meantime, block confirmation
                $paidOverlap = RentRequest::where('property_id', $property->id)
                    ->where('id', '!=', $rentRequest->id)
                    ->where('status', 'paid')
                    ->where(function ($q) use ($rentRequest) {
                        $q->where('check_in', '<', $rentRequest->check_out)
                            ->where('check_out', '>', $rentRequest->check_in);
                    })
                    ->exists();

                if ($paidOverlap) {
                    return $this->error('Property is already booked in this date range.', 409);
                }

                // Consider timezone for payment deadline
                $deadlineHours = config('rent.payment_deadline_hours', 2);
                $timezone = $property->timezone ?? config('app.timezone');
                $paymentDeadline = Carbon::now($timezone)->addHours($deadlineHours);

                // Confirm request and set payment deadline
                $rentRequest->status = 'confirmed';
                $rentRequest->payment_deadline = $paymentDeadline;
                $rentRequest->save();

                // Log the action
                Log::info('Rent request confirmed by owner', [
                    'owner_id' => $owner->id,
                    'request_id' => $rentRequest->id,
                    'payment_deadline' => $paymentDeadline,
                ]);

                // FIXED: Safe notification with error handling
                try {
                    $notification = new \App\Notifications\RentRequestAccepted($rentRequest);
                    Notification::send($rentRequest->user, $notification);
                    
                    // Create UserNotification from websocket data
                    $this->createUserNotificationFromWebsocketData(
                        $rentRequest->user,
                        $notification,
                        NotificationPurpose::RENT_REQUEST_ACCEPTED,
                        $owner->id
                    );
                } catch (Exception $e) {
                    Log::warning('Failed to send confirmation notification', [
                        'error' => $e->getMessage(),
                        'request_id' => $rentRequest->id
                    ]);
                }

                return $this->success('Request confirmed. Renter has ' . $deadlineHours . ' hours to pay.');
            });
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return $this->error('Request not found.', 404);
        } catch (Exception $e) {
            Log::error('confirmRequestByOwner error: ' . $e->getMessage(), [
                'request_id' => $id,
                'owner_id' => $owner->id ?? null,
            ]);
            return $this->error('Failed to confirm request. Try again later.', 500);
        }
    }

    /**
     * Owner rejects request
     */
    public function rejectRequestByOwner(Request $request, $id)
    {
        $owner = $request->user();
        try {
            return DB::transaction(function () use ($id, $owner) {
                $rentRequest = RentRequest::lockForUpdate()->findOrFail($id);

                if ($rentRequest->status !== 'pending') {
                    return $this->error('Only pending requests can be rejected.', 422);
                }

                $property = $rentRequest->property()->lockForUpdate()->first();
                if ($property->owner_id !== $owner->id) {
                    return $this->error('You are not the owner of this property.', 403);
                }

                // Check if owner account is active
                if ($owner->status !== 'active') {
                    return $this->error('Your account must be active to reject requests.', 403);
                }

                $rentRequest->status = 'rejected';
                $rentRequest->blocked_until = Carbon::now()->addMinutes(3);
                $rentRequest->save();

                // Log the action
                Log::info('Rent request rejected by owner', [
                    'owner_id' => $owner->id,
                    'request_id' => $rentRequest->id,
                    'blocked_until' => $rentRequest->blocked_until,
                ]);

                // FIXED: Safe notification with error handling
                try {
                    $notification = new \App\Notifications\OwnerRejectsRequest($rentRequest);
                    Notification::send($rentRequest->user, $notification);
                    
                    // Create UserNotification from websocket data
                    $this->createUserNotificationFromWebsocketData(
                        $rentRequest->user,
                        $notification,
                        NotificationPurpose::OWNER_REJECTS_REQUEST,
                        $owner->id
                    );
                } catch (Exception $e) {
                    Log::warning('Failed to send rejection notification', [
                        'error' => $e->getMessage(),
                        'request_id' => $rentRequest->id
                    ]);
                }

                return $this->success('Request rejected and user temporarily blocked for this property.');
            });
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return $this->error('Request not found.', 404);
        } catch (Exception $e) {
            Log::error('rejectRequestByOwner error: ' . $e->getMessage(), [
                'request_id' => $id,
                'owner_id' => $owner->id ?? null,
            ]);
            return $this->error('Failed to reject request. Try again later.', 500);
        }
    }

    /**
     * Owner cancels a confirmed (unpaid) request
     */
    public function cancelConfirmedByOwner(Request $request, $id)
    {
        $owner = $request->user();
        try {
            return DB::transaction(function () use ($id, $owner) {
                $rentRequest = RentRequest::lockForUpdate()->findOrFail($id);

                if ($rentRequest->status !== 'confirmed') {
                    return $this->error('Only confirmed (unpaid) requests can be cancelled by owner.', 422);
                }

                $property = $rentRequest->property()->lockForUpdate()->first();
                if ($property->owner_id !== $owner->id) {
                    return $this->error('You are not the owner of this property.', 403);
                }

                // Check if owner account is active
                if ($owner->status !== 'active') {
                    return $this->error('Your account must be active to cancel requests.', 403);
                }

                // Ensure renter didn't pay already (double-check)
                $paidExists = Purchase::where('rent_request_id', $rentRequest->id)
                    ->whereIn('status', ['successful'])
                    ->exists();

                if ($paidExists) {
                    return $this->error('Cannot cancel: renter already paid. Process via checkout.', 422);
                }

                $rentRequest->status = 'cancelled_by_owner';
                $rentRequest->save();

                // Log the action
                Log::info('Confirmed request cancelled by owner', [
                    'owner_id' => $owner->id,
                    'request_id' => $rentRequest->id,
                ]);

                // FIXED: Safe notification with error handling
                try {
                    $notification = new \App\Notifications\OwnerRejectsRequest($rentRequest);
                    Notification::send($rentRequest->user, $notification);
                    
                    // Create UserNotification from websocket data
                    $this->createUserNotificationFromWebsocketData(
                        $rentRequest->user,
                        $notification,
                        NotificationPurpose::OWNER_REJECTS_REQUEST,
                        $owner->id
                    );
                } catch (Exception $e) {
                    Log::warning('Failed to send owner cancellation notification', [
                        'error' => $e->getMessage(),
                        'request_id' => $rentRequest->id
                    ]);
                }

                return $this->success('Confirmed request cancelled by owner.');
            });
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return $this->error('Request not found.', 404);
        } catch (Exception $e) {
            Log::error('cancelConfirmedByOwner error: ' . $e->getMessage(), [
                'request_id' => $id,
                'owner_id' => $owner->id ?? null,
            ]);
            return $this->error('Failed to cancel request. Try again later.', 500);
        }
    }

public function payForRequest(Request $request, $id)
{
   

    $user = $request->user();
    if (!$user) return $this->error('Authentication required.', 401);
    if ($user->status !== 'active') return $this->error('Your account must be active to make payments.', 403);

    $idempotencyKey = $request->input('idempotency_key') ?? Str::uuid()->toString();

    try {
        return DB::transaction(function () use ($id, $user, $idempotencyKey) {
            // ========== Validate rent request ==========
            $rentRequest = RentRequest::lockForUpdate()->find($id);
            if (!$rentRequest) return $this->error('Rent request not found.', 404);

            if ($rentRequest->user_id !== $user->id) {
                return $this->error('This request does not belong to you.', 403);
            }

            if ($rentRequest->status !== 'confirmed') {
                return $this->error('Request must be confirmed before payment.', 422);
            }

            if ($rentRequest->payment_deadline && Carbon::now()->gt($rentRequest->payment_deadline)) {
                return $this->error('Payment deadline expired. Please create a new request.', 422);
            }

            // ========== Calculate pricing ==========
            $checkIn  = Carbon::parse($rentRequest->check_in);
            $checkOut = Carbon::parse($rentRequest->check_out);
            $days     = max(1, $checkIn->diffInDays($checkOut));

            $property = Property::lockForUpdate()->find($rentRequest->property_id);
            if (!$property) return $this->error('Property not found.', 404);

            $pricePerNight = $property->price_per_night ?? $property->price ?? $property->daily_rent;
            if (!$pricePerNight || $pricePerNight <= 0) {
                return $this->error('Property pricing not configured.', 422);
            }

            $rentAmount    = $pricePerNight * $days;
            $depositAmount = $pricePerNight;
            $totalAmount   = bcadd($rentAmount, $depositAmount, 2);


            // ========== Wallet handling ==========
            $wallet = Wallet::lockForUpdate()->firstOrCreate(
                ['user_id' => $user->id],
                ['balance' => 0]
            );

            if ($wallet->balance >= $totalAmount) {
                // 💰 Fully wallet-funded
                $before = $wallet->balance;
                $wallet->decrement('balance', $totalAmount);

                WalletTransaction::create([
                    'wallet_id'      => $wallet->id,
                    'amount'         => -$totalAmount,
                    'type'           => 'rent_payment',
                    'ref_id'         => $rentRequest->id,
                    'ref_type'       => 'rent_request',
                    'description'    => 'Rental payment (wallet)',
                    'balance_before' => $before,
                    'balance_after'  => $wallet->balance,
                ]);

                $escrow = EscrowBalance::create([
                    'rent_request_id' => $rentRequest->id,
                    'user_id'         => $user->id,
                    'owner_id'        => $property->owner_id,
                    'rent_amount'     => $rentAmount,
                    'deposit_amount'  => $depositAmount,
                    'total_amount'    => $totalAmount,
                    'status'          => 'locked',
                    'locked_at'       => now(),
                ]);

                $rentRequest->update([
                    'status'            => 'paid',
                    'idempotency_key'   => $idempotencyKey,
                    'payment_gateway'   => 'wallet',
                ]);

// 🔔 Notifications - Database first, then Pusher
                try {
                    $buyerNotification = new \App\Notifications\RentPaidByRenter($rentRequest);
                    $this->createUserNotificationFromWebsocketData(
                        $user,
                        $buyerNotification,
                        NotificationPurpose::PAYMENT_SUCCESSFUL,
                        null
                    );
                } catch (\Exception $e) {
                    Log::warning('Failed to create buyer database notification', ['error' => $e->getMessage()]);
                }

                try {
                    $ownerNotification = new \App\Notifications\RentPaidByRenter($rentRequest);
                    $this->createUserNotificationFromWebsocketData(
                        $property->owner,
                        $ownerNotification,
                        NotificationPurpose::PAYMENT_SUCCESSFUL,
                        $user->id
                    );
                } catch (\Exception $e) {
                    Log::warning('Failed to create owner database notification', ['error' => $e->getMessage()]);
                }

                try {
                    Notification::send($user, $buyerNotification);
                    Notification::send($property->owner, $ownerNotification);
                } catch (\Throwable $e) {
                    Log::warning('Pusher notification failed on rent payForRequest wallet', ['error' => $e->getMessage()]);
                }

                return $this->success('Payment successful via wallet.', [
                    'rent_request'   => $rentRequest,
                    'escrow_balance' => $escrow,
                    'wallet_balance' => $wallet->balance,
                        'total_amount'   => $totalAmount, 

                ]);
            }

            // 💳 Wallet insufficient → Paymob redirect
            $walletUsed = $wallet->balance;
            $shortfall  = $totalAmount - $walletUsed;

            $paymobService = app(\App\Services\PaymobPaymentService::class);
            $paymentKey = $paymobService->createPaymentKey([
                'amount_cents' => intval($shortfall * 100),
                'currency'     => config('payment.default_currency', 'EGP'),
                'user'         => $user,
                'metadata'     => [
                    'rent_request_id' => $rentRequest->id,
                    'user_id'         => $user->id,
                    'wallet_to_use'   => $walletUsed,
                    'idempotency_key' => $idempotencyKey,
                    'flow'            => 'rent_payment',
                ],
            ]);

            $iframeId    = config('payment.paymob_iframe_id');
$redirectUrl = env('PAYMOB_IFRAME_URL') . "?payment_token={$paymentKey}";


            return $this->success('Redirecting to Paymob for payment.', [
                'payment_method' => 'wallet+paymob',
                'redirect_url'   => $redirectUrl,
                'shortfall'      => $shortfall,
                'wallet_balance' => $walletUsed,
                    'total_amount'   => $totalAmount,

            ]);
        });
    } catch (\Throwable $e) {
        Log::error('payForRequest error: '.$e->getMessage(), ['trace' => $e->getTraceAsString()]);
        return $this->error('Payment failed. Please try again.', 500);
    }
}


/** List all requests by user (paginated)
     */
public function listUserRequests(Request $request)
{
    $user = $request->user();
    $perPage = min((int) $request->input('per_page', 20), 12);

    $data = RentRequest::where('user_id', $user->id)
        ->with([
            'property' => function ($query) {
                $query->select('id', 'title', 'location', 'price', 'price_type', 'owner_id');
            },
            'property.owner' => function ($query) {
                $query->select('id', 'first_name', 'last_name', 'phone_number');
            }
        ])
        ->orderBy('created_at', 'desc')
        ->paginate($perPage);

    // Transform results: only include owner info if request is paid
    $data->getCollection()->transform(function ($request) {
        if ($request->status === 'paid' && $request->property && $request->property->owner) {
            $request->property->owner_info = [
                'first_name'   => $request->property->owner->first_name,
                'last_name'    => $request->property->owner->last_name,
                'phone_number' => $request->property->owner->phone_number,
            ];
        } else {
            $request->property->owner_info = null;
        }
        unset($request->property->owner); 
        return $request;
    });

    return $this->success('User requests retrieved.', $data);
}

    /**
     * List all requests for an owner (across their properties)
     */
public function listOwnerRequests(Request $request)
{
    $owner = $request->user();
    $perPage = min((int) $request->input('per_page', 20), 12); 

    // Get owned property ids
    $propertyIds = Property::where('owner_id', $owner->id)->pluck('id');

    $data = RentRequest::whereIn('property_id', $propertyIds)
        ->with([
            'property' => function ($query) {
                $query->select('id', 'title', 'location', 'price', 'price_type');
            },
            'user' => function ($query) {
                $query->select('id', 'first_name', 'last_name', 'phone_number');
            }
        ])
        ->orderBy('created_at', 'desc')
        ->paginate($perPage);

    // Transform: only return user details if status is "paid"
    $data->getCollection()->transform(function ($request) {
        if ($request->status === 'paid' && $request->user) {
            $request->user_info = [
                'first_name'   => $request->user->first_name,
                'last_name'    => $request->user->last_name,
                'phone_number' => $request->user->phone_number,
            ];
        } else {
            $request->user_info = [
                'first_name' => $request->user->first_name ?? null,
                'last_name'  => $request->user->last_name ?? null,
                'phone_number' => null, // hide number unless paid
            ];
        }
        unset($request->user); // don't leak full relation
        return $request;
    });

    return $this->success('Owner requests retrieved.', $data);
}

    /**
     * System cron: cancel unpaid confirmed requests past payment_deadline
     */
    public function autoCancelUnpaidRequests()
    {
        try {
            $now = Carbon::now();
            $expired = RentRequest::where('status', 'confirmed')
                ->whereNotNull('payment_deadline')
                ->where('payment_deadline', '<', $now)
                ->get();

            $cancelledCount = 0;

            foreach ($expired as $r) {
                try {
                    DB::transaction(function () use ($r, &$cancelledCount) {
                        // Re-check status inside transaction
                        $rentRequest = RentRequest::lockForUpdate()->find($r->id);
                        if (!$rentRequest || $rentRequest->status !== 'confirmed') {
                            return;
                        }

                        // Mark cancelled
                        $rentRequest->status = 'cancelled';
                        $rentRequest->save();

                        $cancelledCount++;

                        // Log the action
                        Log::info('Request auto-cancelled due to payment deadline', [
                            'request_id' => $rentRequest->id,
                            'payment_deadline' => $rentRequest->payment_deadline,
                        ]);

                        // Trigger the broadcast event for cancellation
                        broadcast(new RequestAutoCancelledEvent($rentRequest));

                        // Safe notifications
                        try {
                            $userNotification = new \App\Notifications\RequestAutoCancelled($rentRequest);
                            Notification::send($rentRequest->user, $userNotification);
                            
                            // Create UserNotification from websocket data for user
                            $this->createUserNotificationFromWebsocketData(
                                $rentRequest->user,
                                $userNotification,
                                NotificationPurpose::REQUEST_AUTO_CANCELLED,
                                null
                            );

                            $ownerNotification = new \App\Notifications\RequestAutoCancelledBySystem($rentRequest);
                            Notification::send($rentRequest->property->owner, $ownerNotification);
                            
                            // Create UserNotification from websocket data for owner
                            $this->createUserNotificationFromWebsocketData(
                                $rentRequest->property->owner,
                                $ownerNotification,
                                NotificationPurpose::REQUEST_AUTO_CANCELLED_BY_SYSTEM,
                                null
                            );
                        } catch (Exception $e) {
                            Log::warning('Failed to send auto-cancellation notifications', [
                                'error' => $e->getMessage(),
                                'request_id' => $rentRequest->id
                            ]);
                        }
                    });
                } catch (Exception $e) {
                    Log::error('Error auto-cancelling individual request', [
                        'request_id' => $r->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            Log::info('Auto-cancel job completed', [
                'total_expired' => $expired->count(),
                'successfully_cancelled' => $cancelledCount,
            ]);

            return $this->success('Auto-cancel job processed.', [
                'total_expired' => $expired->count(),
                'cancelled' => $cancelledCount,
            ]);
        } catch (Exception $e) {
            Log::error('autoCancelUnpaidRequests error: ' . $e->getMessage());
            return $this->error('Auto-cancel job failed.', 500);
        }
    }

    /**
     * Get single rent request details (for user or owner)
     */
public function getRequestDetails(Request $request, $id)
{
    $user = $request->user();

    try {
        $rentRequest = RentRequest::with([
            'property' => function ($query) {
                $query->select('id', 'title', 'location', 'price', 'price_type', 'owner_id');
            },
            'user' => function ($query) {
                $query->select('id', 'first_name', 'last_name', 'email', 'phone_number');
            },
            // 'purchases' => function ($query) {
            //     $query->select('id', 'rent_request_id', 'amount', 'status', 'transaction_ref', 'created_at');
            // },
            'property.owner' => function ($query) {
                $query->select('id', 'first_name', 'last_name', 'phone_number');
            }
        ])->findOrFail($id);

        // Check access
        $isOwner  = $rentRequest->property->owner_id === $user->id;
        $isRenter = $rentRequest->user_id === $user->id;

        if (!$isOwner && !$isRenter) {
            return $this->error('You do not have access to this request.', 403);
        }

        // If viewer is renter
        if ($isRenter) {
            // Only show owner details if status is paid
            if ($rentRequest->status !== 'paid') {
                unset($rentRequest->property->owner);
            }
            // Always hide owner email, created_at, etc.
            if ($rentRequest->property->owner) {
                $rentRequest->property->owner->makeHidden(['email_verified_at', 'created_at', 'updated_at']);
            }
        }

        // If viewer is owner
        if ($isOwner) {
            // Owner sees renter but without sensitive system fields
            if ($rentRequest->user) {
                $rentRequest->user->makeHidden(['email_verified_at', 'created_at', 'updated_at']);
            }
        }

        return $this->success('Request details retrieved.', $rentRequest);

    } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
        return $this->error('Request not found.', 404);
    } catch (Exception $e) {
        Log::error('getRequestDetails error: ' . $e->getMessage(), [
            'request_id' => $id,
            'user_id'    => $user->id,
        ]);
        return $this->error('Failed to retrieve request details.', 500);
    }
}

    /**
     * Get request statistics for dashboard
     */
    public function getRequestStats(Request $request)
    {
        $user = $request->user();

        try {
            // User stats (as renter)
            $userStats = [
                'pending' => RentRequest::where('user_id', $user->id)->where('status', 'pending')->count(),
                'confirmed' => RentRequest::where('user_id', $user->id)->where('status', 'confirmed')->count(),
                'paid' => RentRequest::where('user_id', $user->id)->where('status', 'paid')->count(),
                'cancelled' => RentRequest::where('user_id', $user->id)
                    ->whereIn('status', ['cancelled', 'cancelled_by_owner'])->count(),
                'rejected' => RentRequest::where('user_id', $user->id)->where('status', 'rejected')->count(),
                'total' => RentRequest::where('user_id', $user->id)->count(),
            ];

            // Owner stats (as property owner)
            $propertyIds = Property::where('owner_id', $user->id)->pluck('id');
            $ownerStats = [
                'pending' => RentRequest::whereIn('property_id', $propertyIds)->where('status', 'pending')->count(),
                'confirmed' => RentRequest::whereIn('property_id', $propertyIds)->where('status', 'confirmed')->count(),
                'paid' => RentRequest::whereIn('property_id', $propertyIds)->where('status', 'paid')->count(),
                'cancelled' => RentRequest::whereIn('property_id', $propertyIds)
                    ->whereIn('status', ['cancelled', 'cancelled_by_owner'])->count(),
                'rejected' => RentRequest::whereIn('property_id', $propertyIds)->where('status', 'rejected')->count(),
                'total' => RentRequest::whereIn('property_id', $propertyIds)->count(),
            ];

            return $this->success('Request statistics retrieved.', [
                'as_renter' => $userStats,
                'as_owner' => $ownerStats,
            ]);

        } catch (Exception $e) {
            Log::error('getRequestStats error: ' . $e->getMessage(), [
                'user_id' => $user->id,
            ]);
            return $this->error('Failed to retrieve statistics.', 500);
        }
    }

    /**
 * Get all unavailable booking date ranges for a property
 */
public function getUnavailableDates($propertyId)
{
    try {
        // Define which statuses should block dates
        $blockingStatuses = ['paid','pending','confirmed']; // add 'approved' if you use it

        $dates = RentRequest::where('property_id', $propertyId)
            ->whereIn('status', $blockingStatuses)
            ->get(['check_in', 'check_out']);

        return response()->json([
            'success' => true,
            'data' => $dates
        ]);
    } catch (\Exception $e) {
        \Log::error('getUnavailableDates error: ' . $e->getMessage(), [
            'property_id' => $propertyId,
        ]);

        return response()->json([
            'success' => false,
            'message' => 'Failed to fetch unavailable dates.'
        ], 500);
    }
}

}