<?php

namespace App\Http\Controllers;

use App\Events\RequestAutoCancelledEvent;
use App\Models\Purchase;
use App\Models\RentRequest;
use App\Models\Property;
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

    if ($recentRequests >= 5) {
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
                Notification::send($property->owner, new \App\Notifications\RentRequested($rentRequest));
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
                $rentRequest->cooldown_expires_at = Carbon::now()->addHours(48); // business rule
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
                Notification::send($rentRequest->property->owner, new \App\Notifications\UserRejectsRequest($rentRequest));
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
                    Notification::send($rentRequest->user, new \App\Notifications\RentRequestAccepted($rentRequest));
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
                $rentRequest->blocked_until = Carbon::now()->addDays(3);
                $rentRequest->save();

                // Log the action
                Log::info('Rent request rejected by owner', [
                    'owner_id' => $owner->id,
                    'request_id' => $rentRequest->id,
                    'blocked_until' => $rentRequest->blocked_until,
                ]);

                // FIXED: Safe notification with error handling
                try {
                    Notification::send($rentRequest->user, new \App\Notifications\UserRejectsRequest($rentRequest));
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
                    Notification::send($rentRequest->user, new \App\Notifications\OwnerRejectsRequest($rentRequest));
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
    $validator = \Validator::make($request->all(), [
        'payment_method_token' => 'nullable|string', // nullable because wallet might cover full amount
        'idempotency_key'      => 'nullable|string',
        'expected_total'       => 'required|numeric|min:0',
    ]);

    if ($validator->fails()) {
        return $this->error('Validation failed.', 422, $validator->errors());
    }

    $user = $request->user();
    if (!$user) {
        return $this->error('Authentication required.', 401);
    }

    if ($user->status !== 'active') {
        return $this->error('Your account must be active to make payments.', 403);
    }

    $idempotencyKey = $request->input('idempotency_key') ?? Str::uuid()->toString();
    $paymentToken   = $request->input('payment_method_token');
    $expectedTotal  = $request->input('expected_total');

    try {
        return DB::transaction(function () use ($id, $user, $idempotencyKey, $paymentToken, $expectedTotal) {
            // Lock rent_request
            $rentRequest = RentRequest::lockForUpdate()->find($id);
            if (!$rentRequest) {
                return $this->error('Rent request not found.', 404);
            }
            
            if ($rentRequest->user_id !== $user->id) {
                return $this->error('This request does not belong to you.', 403);
            }
            
            if ($rentRequest->status !== 'confirmed') {
                return $this->error('Request must be confirmed before payment.', 422);
            }

            // Check payment deadline
            if ($rentRequest->payment_deadline && Carbon::now()->gt($rentRequest->payment_deadline)) {
                return $this->error('Payment deadline expired. Please create a new request.', 422);
            }

            // Idempotency check
            $existingPurchase = Purchase::where('rent_request_id', $rentRequest->id)
                ->where('idempotency_key', $idempotencyKey)
                ->where('status', 'successful')
                ->first();

            if ($existingPurchase) {
                return $this->success('Payment already processed.', $existingPurchase);
            }

            // Lock property and prevent double booking
            $property = Property::lockForUpdate()->find($rentRequest->property_id);
            if (!$property) {
                return $this->error('Property not found.', 404);
            }

            $conflict = RentRequest::where('property_id', $property->id)
                ->where('id', '!=', $rentRequest->id)
                ->where('status', 'paid')
                ->lockForUpdate()
                ->where(function ($q) use ($rentRequest) {
                    $q->where('check_in', '<', $rentRequest->check_out)
                      ->where('check_out', '>', $rentRequest->check_in);
                })
                ->exists();

            if ($conflict) {
                return $this->error('Property has already been booked for these dates.', 409);
            }

            // CALCULATE PRICING ON-THE-FLY (since table doesn't have pricing columns)
            $checkIn = Carbon::parse($rentRequest->check_in);
            $checkOut = Carbon::parse($rentRequest->check_out);
            $days = $checkIn->diffInDays($checkOut);
            if ($days <= 0) $days = 1;

            // Get property pricing - try multiple fields
            $pricePerNight = null;
            if (!empty($property->price_per_night) && $property->price_per_night > 0) {
                $pricePerNight = $property->price_per_night;
            } elseif (!empty($property->price) && $property->price > 0) {
                $pricePerNight = $property->price;
            } elseif (!empty($property->daily_rent) && $property->daily_rent > 0) {
                $pricePerNight = $property->daily_rent;
            } else {
                Log::error('Property has no valid pricing data', [
                    'property_id' => $property->id,
                    'price_per_night' => $property->price_per_night ?? 'null',
                    'price' => $property->price ?? 'null',
                    'daily_rent' => $property->daily_rent ?? 'null',
                ]);
                return $this->error('Property pricing is not configured. Please contact support.', 422);
            }

            // Calculate rent amount
            $rentAmount = $pricePerNight * $days;
            
            // Calculate deposit (simple approach - 1 night's rent)
            $depositAmount = $pricePerNight;

            $totalAmount = bcadd($rentAmount, $depositAmount, 2);

            // Debug logging
            Log::info('Payment calculation (on-the-fly)', [
                'rent_request_id' => $rentRequest->id,
                'property_id' => $property->id,
                'days' => $days,
                'price_per_night' => $pricePerNight,
                'rent_amount' => $rentAmount,
                'deposit_amount' => $depositAmount,
                'total_amount' => $totalAmount,
                'expected_total_from_client' => $expectedTotal,
            ]);

            if ($totalAmount <= 0) {
                return $this->error('Invalid payment calculation. Please contact support.', 422);
            }

            // Verify the expected total matches (with some tolerance for floating point)
            if (abs($totalAmount - $expectedTotal) > 0.01) {
                return $this->error(
                    'Price mismatch. Expected: $' . number_format($totalAmount, 2) . 
                    ', Received: $' . number_format($expectedTotal, 2) . 
                    '. Please refresh and try again.', 
                    422
                );
            }

            // WALLET FIRST LOGIC - exactly as in your implementation
            $wallet = Wallet::lockForUpdate()->firstOrCreate(['user_id' => $user->id], ['balance' => 0]);
            $walletBalance = $wallet->balance;
            $usedFromWallet = 0;
            $remainingToCharge = $totalAmount;

            if ($walletBalance > 0) {
                if ($walletBalance >= $totalAmount) {
                    // Wallet covers everything
                    $usedFromWallet = $totalAmount;
                    $remainingToCharge = 0;

                    $wallet->balance = bcsub($wallet->balance, $totalAmount, 2);
                    $wallet->save();

                    WalletTransaction::create([
                        'wallet_id' => $wallet->id,
                        'amount'    => -$totalAmount,
                        'type'      => 'payment',
                        'ref_id'    => $rentRequest->id,
                        'ref_type'  => 'rent_request',
                        'description' => 'Payment for rental booking (full wallet)',
                        'balance_before' => $walletBalance,
                        'balance_after' => $wallet->balance,
                    ]);
                } else {
                    // Partial wallet + gateway
                    $usedFromWallet = $walletBalance;
                    $remainingToCharge = bcsub($totalAmount, $walletBalance, 2);

                    $wallet->balance = 0;
                    $wallet->save();

                    WalletTransaction::create([
                        'wallet_id' => $wallet->id,
                        'amount'    => -$usedFromWallet,
                        'type'      => 'payment',
                        'ref_id'    => $rentRequest->id,
                        'ref_type'  => 'rent_request',
                        'description' => 'Partial payment for rental booking (wallet portion)',
                        'balance_before' => $walletBalance,
                        'balance_after' => $wallet->balance,
                    ]);
                }
            }

            // Gateway charge if needed
            $transactionRef = Str::uuid()->toString();
            $gatewayUsed = null;

            if ($remainingToCharge > 0) {
                if (!$paymentToken) {
                    return $this->error('Payment method is required for remaining balance.', 422);
                }

                try {
                    $gatewayResponse = app('App\\Services\\PaymentGateway')->charge([
                        'amount'        => $remainingToCharge,
                        'currency'      => config('payment.default_currency', 'EGP'),
                        'payment_token' => $paymentToken,
                        'idempotency'   => $idempotencyKey,
                        'metadata'      => [
                            'rent_request_id' => $rentRequest->id,
                            'user_id'         => $user->id,
                            'property_id'     => $property->id,
                        ],
                    ]);

                    if (empty($gatewayResponse) || empty($gatewayResponse['success'])) {
                        // Rollback wallet deduction if gateway fails
                        if ($usedFromWallet > 0) {
                            $wallet->balance = bcadd($wallet->balance, $usedFromWallet, 2);
                            $wallet->save();
                        }
                        
                        $errMsg = $gatewayResponse['message'] ?? 'Payment failed at gateway.';
                        return $this->error($errMsg, 422);
                    }

                    $transactionRef = $gatewayResponse['transaction_ref'] ?? ($gatewayResponse['id'] ?? $transactionRef);
                    $gatewayUsed = $gatewayResponse['gateway'] ?? 'Unknown';

                } catch (Exception $e) {
                    // Rollback wallet deduction if gateway fails
                    if ($usedFromWallet > 0) {
                        $wallet->balance = bcadd($wallet->balance, $usedFromWallet, 2);
                        $wallet->save();
                    }
                    
                    return $this->error('Payment provider error: ' . $e->getMessage(), 502);
                }
            }

            // Create purchase records (split rent & deposit)
            $depositPurchase = Purchase::create([
                'rent_request_id' => $rentRequest->id,
                'user_id'         => $user->id,
                'property_id'     => $property->id,
                'amount'          => $depositAmount,
                'payment_type'    => 'deposit',
                'status'          => 'successful',
                'payment_gateway' => $remainingToCharge > 0 ? $gatewayUsed : 'Wallet',
                'transaction_ref' => $transactionRef,
                'idempotency_key' => $idempotencyKey,
                'metadata' => [
                    'wallet_used' => min($usedFromWallet, $depositAmount),
                    'gateway_charged' => max(0, bcsub($depositAmount, min($usedFromWallet, $depositAmount), 2)),
                    'total_amount' => $totalAmount,
                    'calculated_on_payment' => true, // Flag to indicate this was calculated at payment time
                    'days' => $days,
                    'price_per_night' => $pricePerNight,
                ],
            ]);

            $rentPurchase = Purchase::create([
                'rent_request_id' => $rentRequest->id,
                'user_id'         => $user->id,
                'property_id'     => $property->id,
                'amount'          => $rentAmount,
                'payment_type'    => 'rent',
                'status'          => 'successful',
                'payment_gateway' => $remainingToCharge > 0 ? $gatewayUsed : 'Wallet',
                'transaction_ref' => $transactionRef,
                'idempotency_key' => $idempotencyKey,
                'metadata' => [
                    'wallet_used' => max(0, bcsub($usedFromWallet, $depositAmount, 2)),
                    'gateway_charged' => $remainingToCharge > 0 ? $remainingToCharge - max(0, bcsub($usedFromWallet, $depositAmount, 2)) : 0,
                    'total_amount' => $totalAmount,
                    'calculated_on_payment' => true,
                    'days' => $days,
                    'price_per_night' => $pricePerNight,
                ],
            ]);

            // Create escrow balance for checkout system
            EscrowBalance::create([
                'rent_request_id' => $rentRequest->id,
                'user_id'         => $user->id,
                'rent_amount'     => $rentAmount,
                'deposit_amount'  => $depositAmount,
                'total_amount'    => $totalAmount,
                'status'          => 'locked',
                'locked_at'       => Carbon::now(),
            ]);

            // Update rent request status
            $rentRequest->status = 'paid';
            $rentRequest->save();

            // Schedule rent release 24h after check-in
            $releaseAt = Carbon::parse($rentRequest->check_in)->addHours(24);

            \App\Jobs\ReleaseRentJob::dispatch($rentRequest->id)->delay($releaseAt);

            // Send notifications
            try {
                Notification::send($rentRequest->property->owner, new \App\Notifications\PaymentSuccessful($rentRequest));
                Notification::send($user, new \App\Notifications\PaymentSuccessful($rentRequest));
            } catch (Exception $e) {
                Log::warning('Failed to send payment success notifications', [
                    'error' => $e->getMessage(),
                    'rent_request_id' => $rentRequest->id
                ]);
            }

            Log::info('Payment successful', [
                'rent_request_id' => $rentRequest->id,
                'user_id' => $user->id,
                'total_amount' => $totalAmount,
                'wallet_contribution' => $usedFromWallet,
                'gateway_charged' => $remainingToCharge,
                'transaction_ref' => $transactionRef,
            ]);

            return $this->success('Payment successful. Booking confirmed.', [
                'rent_request'     => $rentRequest->fresh(),
                'deposit_purchase' => $depositPurchase,
                'rent_purchase'    => $rentPurchase,
                'escrow_balance'   => EscrowBalance::where('rent_request_id', $rentRequest->id)->first(),
                'transaction_ref'  => $transactionRef,
                'used_from_wallet' => $usedFromWallet,
                'charged_gateway'  => $remainingToCharge,
                'calculation_details' => [
                    'days' => $days,
                    'price_per_night' => $pricePerNight,
                    'rent_amount' => $rentAmount,
                    'deposit_amount' => $depositAmount,
                ],
            ], 200);
        });
    } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
        return $this->error('Request not found.', 404);
    } catch (Exception $e) {
        Log::error('payForRequest error: ' . $e->getMessage(), [
            'trace'      => $e->getTraceAsString(),
            'request_id' => $id,
            'user_id'    => $user->id ?? null,
        ]);
        return $this->error('Payment processing failed. Please try again later.', 500);
    }
}/** List all requests by user (paginated)
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
        unset($request->user); // don’t leak full relation
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
                            Notification::send($rentRequest->user, new \App\Notifications\RequestAutoCancelled($rentRequest));
                            Notification::send($rentRequest->property->owner, new \App\Notifications\RequestAutoCancelledBySystem($rentRequest));
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
}