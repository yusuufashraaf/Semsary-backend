<?php

namespace App\Http\Controllers;

use App\Models\Property;
use App\Models\PropertyPurchase;
use App\Models\PropertyEscrow;
use App\Models\Wallet;
use App\Models\RentRequest;
use App\Models\WalletTransaction;
use App\Models\UserNotification;
use App\Enums\NotificationPurpose;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Carbon\Carbon;
use Exception;

class PropertyPurchaseController extends Controller
{
    // ====================================================
    // Helpers for uniform responses
    // ====================================================
    protected function success($message, $data = null, $status = 200)
    {
        $payload = ['success' => true, 'message' => $message];
        if (!is_null($data)) {
            $payload['data'] = $data;
        }
        return response()->json($payload, $status);
    }

    protected function error($message, $status = 422, $details = null)
    {
        $payload = ['success' => false, 'message' => $message];
        if (!is_null($details)) {
            $payload['errors'] = $details;
        }
        return response()->json($payload, $status);
    }

    // ====================================================
    // Persist notifications into user_notifications table
    // ====================================================
    private function createUserNotificationFromWebsocketData(
        $recipient,
        $notificationClass,
        NotificationPurpose $purpose,
        $senderId = null
    ) {
        try {
            $notificationData = null;
            if (method_exists($notificationClass, 'toDatabase')) {
                $notificationData = $notificationClass->toDatabase($recipient);
            } elseif (method_exists($notificationClass, 'toBroadcast')) {
                $broadcastData = $notificationClass->toBroadcast($recipient);
                $notificationData = $broadcastData->data ?? $broadcastData;
            }

            $entityId = $notificationData['purchase_id'] ?? $notificationData['property_id'] ?? null;
            $message  = $notificationData['message'] ?? 'New notification';

            UserNotification::create([
                'user_id'   => $recipient->id,
                'sender_id' => $senderId,
                'entity_id' => $entityId,
                'purpose'   => $purpose,
                'title'     => $purpose->label(),
                'message'   => $message,
                'is_read'   => false,
            ]);
        } catch (Exception $e) {
            Log::warning('Failed to create UserNotification', [
                'error' => $e->getMessage(),
                'recipient_id' => $recipient->id ?? null,
            ]);
        }
    }

    // ====================================================
    // PAY FOR PROPERTY
    // ====================================================
public function payForOwn(Request $request, $id)
{
    $validator = \Validator::make($request->all(), [
        'idempotency_key' => 'nullable|string',
        'expected_total'  => 'required|numeric|min:0',
    ]);

    if ($validator->fails()) {
        return $this->error('Validation failed.', 422, $validator->errors());
    }

    $user = $request->user();
    if (!$user) return $this->error('Authentication required.', 401);
    if ($user->status !== 'active') return $this->error('Your account must be active.', 403);

    $idempotencyKey = $request->input('idempotency_key') ?? Str::uuid()->toString();
    $expectedTotal  = $request->input('expected_total');

    try {
        return DB::transaction(function () use ($id, $user, $idempotencyKey, $expectedTotal) {
            // Validate property
            $property = Property::lockForUpdate()->find($id);
            if (!$property) return $this->error('Property not found.', 404);
            if ($property->status !== 'sale' || $property->property_state !== 'Valid') {
                return $this->error('This property is not valid for sale.', 422);
            }
            if ($property->owner_id === $user->id) {
                return $this->error('You cannot buy your own property.', 422);
            }

            $seller = $property->owner;
            if (!$seller || $seller->status !== 'active') {
                return $this->error('Property owner is not active.', 422);
            }

            // Price check
            $totalAmount = $property->price;
            if (abs($totalAmount - $expectedTotal) > 0.01) {
                return $this->error('Price mismatch.', 422);
            }

            // Wallet check
            $wallet = Wallet::lockForUpdate()->firstOrCreate(
                ['user_id' => $user->id],
                ['balance' => 0]
            );

            $walletBalance = $wallet->balance;

            if ($walletBalance < $totalAmount) {
                // Not enough balance → Paymob flow
                $shortfall = $totalAmount - $walletBalance;

                $paymob = app(\App\Services\PaymobPaymentService::class);
                $paymentKey = $paymob->createPaymentKey([
                    'amount_cents' => intval($shortfall * 100),
                    'currency'     => 'EGP',
                    'user'         => $user,
                    'metadata'     => [
                        'property_id'    => $property->id,
                        'buyer_id'       => $user->id,
                        'seller_id'      => $property->owner_id,
                        'wallet_to_use'  => $walletBalance,
                        'idempotency_key'=> $idempotencyKey,
                        'flow'           => 'property_purchase',
                    ],
                ]);

                return $this->success('Redirecting to Paymob for remaining balance.', [
                    'payment_method' => 'wallet+paymob',
                    'iframe_url'     => env('PAYMOB_IFRAME_URL') . '?payment_token=' . $paymentKey,
                    'shortfall'      => $shortfall,
                    'wallet_balance' => $walletBalance,
                ]);
            }

            // Fully wallet funded → deduct and finalize instantly
            $wallet->decrement('balance', $totalAmount);

            $purchase = PropertyPurchase::create([
                'property_id'     => $property->id,
                'buyer_id'        => $user->id,
                'seller_id'       => $property->owner_id,
                'amount'          => $totalAmount,
                'status'          => 'paid',
                'payment_gateway' => 'wallet',
                'transaction_ref' => Str::uuid()->toString(),
                'idempotency_key' => $idempotencyKey,
                'metadata'        => [
                    'wallet_to_use'  => $totalAmount,
                    'payment_method' => 'wallet',
                ],
            ]);

            //  Always create escrow
            $escrow = PropertyEscrow::create([
                'property_purchase_id'         => $purchase->id,
                    'property_id'          => $property->id,   

                'buyer_id'            => $user->id,
                'seller_id'           => $property->owner_id,
                'amount'              => $totalAmount,
                'status'              => 'locked',
                'locked_at'           => now(),
                'scheduled_release_at'=> now()->addDays(3), // configurable cancellation window
            ]);

            // 🔔 Send notifications (buyer & seller)
            try {
                $buyerNotification = new \App\Notifications\PropertyPurchaseSuccessful($purchase);
                \Notification::send($user, $buyerNotification);

                $sellerNotification = new \App\Notifications\PropertyPurchaseSuccessful($purchase);
                \Notification::send($purchase->seller, $sellerNotification);
            } catch (\Exception $e) {
                \Log::warning('Notification failed on payForOwn success', [
                    'error' => $e->getMessage()
                ]);
            }

            return $this->success('Purchase successful via wallet.', [
                'purchase'       => $purchase,
                'escrow'         => $escrow,
                'wallet_balance' => $wallet->balance,
            ]);
        });
    } catch (\Throwable $e) {
        Log::error('payForOwn error: '.$e->getMessage(), ['trace' => $e->getTraceAsString()]);
        return $this->error('Property purchase failed.', 500);
    }
}


    // ====================================================
    // CANCEL PURCHASE
    // ====================================================
public function cancelPurchase(Request $request, $purchaseId)
{
    $user = $request->user();
    if (!$user) {
        return $this->error('Authentication required.', 401);
    }

    $purchase = PropertyPurchase::with('escrow', 'property', 'seller')
        ->where('id', $purchaseId)
        ->first();

    if (!$purchase) {
        return $this->error('Purchase not found.', 404);
    }

    if ($purchase->buyer_id !== $user->id) {
        return $this->error('Only the buyer can cancel this purchase.', 403);
    }

    $escrow = $purchase->escrow;

    // Case 1: Pending purchase (no escrow yet, waiting for Paymob)
    if ($purchase->status === 'pending' && !$escrow) {
        $purchase->update(['status' => 'cancelled']);
        $purchase->property->update([
            'status'           => 'sale',
            'property_state'   => 'Valid',
            'pending_buyer_id' => null,
        ]);

        try {
            $buyerNotification = new \App\Notifications\PropertyPurchaseCancelled($purchase);
            Notification::send($user, $buyerNotification);

            $sellerNotification = new \App\Notifications\PurchaseCancelledByBuyer($purchase);
            Notification::send($purchase->seller, $sellerNotification);
        } catch (\Exception $e) {
            Log::warning('Notification failed on cancelPurchase (pending)', [
                'error' => $e->getMessage()
            ]);
        }

        return $this->success('Pending purchase cancelled successfully.');
    }

    // Case 2: Escrow exists and is locked (covers paid and paymob-funded)
    if ($escrow && $escrow->status === 'locked') {
        if ($escrow->scheduled_release_at->isPast()) {
            return $this->error('Cancellation window has expired.', 422);
        }

        return DB::transaction(function () use ($purchase, $escrow, $user) {
            $wallet = Wallet::lockForUpdate()->firstOrCreate(
                ['user_id' => $user->id],
                ['balance' => 0]
            );
            $before = $wallet->balance;
            $wallet->increment('balance', $escrow->amount);

            WalletTransaction::create([
                'wallet_id'       => $wallet->id,
                'amount'          => $escrow->amount,
                'type'            => 'refund',
                'ref_id'          => $purchase->id,
                'ref_type'        => 'property_purchase',
                'description'     => 'Purchase cancelled - escrow refunded',
                'balance_before'  => $before,
                'balance_after'   => $wallet->balance,
            ]);

            $purchase->property->update([
                'status'           => 'sale',
                'property_state'   => 'Valid',
                'pending_buyer_id' => null,
            ]);

            $escrow->update([
                'status'         => 'refunded_to_buyer',
                'released_at'    => now(),
                'release_reason' => 'buyer_cancelled',
            ]);

            $purchase->update(['status' => 'cancelled']);

            try {
                $buyerNotification = new \App\Notifications\PropertyPurchaseCancelled($purchase);
                Notification::send($user, $buyerNotification);

                $sellerNotification = new \App\Notifications\PurchaseCancelledByBuyer($purchase);
                Notification::send($purchase->seller, $sellerNotification);
            } catch (\Exception $e) {
                Log::warning('Notification failed on cancelPurchase (escrow)', [
                    'error' => $e->getMessage()
                ]);
            }

            return $this->success('Purchase cancelled and money refunded.');
        });
    }

    // Otherwise: cannot cancel (already completed or escrow released)
    return $this->error('Purchase cannot be cancelled.', 422);
}


    // ====================================================
    // ALL USER TRANSACTIONS
    // ====================================================
    public function getAllUserTransactions(Request $request)
    {
        $user = $request->user();
        if (!$user) {
            return $this->error('Authentication required.', 401);
        }

        $wallet = Wallet::with(['transactions' => function ($q) {
            $q->orderBy('created_at', 'desc');
        }])->where('user_id', $user->id)->first();

        $purchases = PropertyPurchase::with(['property', 'escrow'])
            ->where(function ($q) use ($user) {
                $q->where('buyer_id', $user->id)
                  ->orWhere('seller_id', $user->id);
            })
            ->orderBy('created_at', 'desc')
            ->get();

        $rents = RentRequest::with(['property', 'escrow'])
            ->where(function ($q) use ($user) {
                $q->where('user_id', $user->id)
                  ->orWhereHas('property', function ($qq) use ($user) {
                      $qq->where('owner_id', $user->id);
                  });
            })
            ->orderBy('created_at', 'desc')
            ->get();

        return $this->success('All financial data retrieved.', [
            'wallet'    => $wallet,
            'purchases' => $purchases,
            'rents'     => $rents,
        ]);
    }

    /**
 * Get user's property purchases
 * GET /api/user/purchases
 */
public function getUserPurchases(Request $request)
{
    $user = $request->user();
    if (!$user) {
        return $this->error('Authentication required.', 401);
    }

    try {
        // Get purchases where user is the buyer
        $purchases = PropertyPurchase::with(['property', 'escrow', 'seller'])
            ->where('buyer_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->get();

        // Transform the data to include property details
        $transformedPurchases = $purchases->map(function ($purchase) {
            return [
                'id' => $purchase->id,
                'property_id' => $purchase->property_id,
                'buyer_id' => $purchase->buyer_id,
                'seller_id' => $purchase->seller_id,
                'amount' => $purchase->amount,
                'status' => $purchase->status,
                'payment_gateway' => $purchase->payment_gateway,
                'transaction_ref' => $purchase->transaction_ref,
                'idempotency_key' => $purchase->idempotency_key,
                'cancellation_deadline' => $purchase->cancellation_deadline,
                'metadata' => $purchase->metadata,
                'created_at' => $purchase->created_at,
                'updated_at' => $purchase->updated_at,
                'property' => [
                    'id' => $purchase->property->id,
                    'title' => $purchase->property->title,
                    'price' => $purchase->property->price,
                    'property_state' => $purchase->property->property_state,
                    'status' => $purchase->property->status,
                ],
                'escrow' => $purchase->escrow ? [
                    'id' => $purchase->escrow->id,
                    'status' => $purchase->escrow->status,
                    'amount' => $purchase->escrow->amount,
                    'locked_at' => $purchase->escrow->locked_at,
                    'scheduled_release_at' => $purchase->escrow->scheduled_release_at,
                    'released_at' => $purchase->escrow->released_at,
                ] : null,
                'seller' => [
                    'id' => $purchase->seller->id,
                    'first_name' => $purchase->seller->first_name,
                    'last_name' => $purchase->seller->last_name,
                    'email' => $purchase->seller->email,
                ]
            ];
        });

        return $this->success('User purchases retrieved successfully.', [
            'purchases' => $transformedPurchases,
            'count' => $transformedPurchases->count()
        ]);

    } catch (\Throwable $e) {
        Log::error('getUserPurchases error: ' . $e->getMessage(), [
            'user_id' => $user->id,
            'trace' => $e->getTraceAsString()
        ]);
        return $this->error('Failed to retrieve purchases.', 500);
    }
}

/**
 * Get specific purchase by property ID (for current user)
 * GET /api/properties/{propertyId}/purchase
 */
public function getUserPurchaseForProperty(Request $request, $propertyId)
{
    $user = $request->user();
    if (!$user) {
        return $this->error('Authentication required.', 401);
    }

    try {
        $purchase = PropertyPurchase::with(['property', 'escrow', 'seller'])
            ->where('buyer_id', $user->id)
            ->where('property_id', $propertyId)
            ->whereNotIn('status', ['cancelled', 'refunded'])
            ->first();

        if (!$purchase) {
            // Return success with `purchase: null`
            return $this->success('No active purchase found for this property.', [
                'purchase' => null
            ]);
        }

        $transformedPurchase = [
            'id' => $purchase->id,
            'property_id' => $purchase->property_id,
            'buyer_id' => $purchase->buyer_id,
            'seller_id' => $purchase->seller_id,
            'amount' => $purchase->amount,
            'status' => $purchase->status,
            'payment_gateway' => $purchase->payment_gateway,
            'transaction_ref' => $purchase->transaction_ref,
            'idempotency_key' => $purchase->idempotency_key,
            'cancellation_deadline' => $purchase->cancellation_deadline,
            'metadata' => $purchase->metadata,
            'created_at' => $purchase->created_at,
            'updated_at' => $purchase->updated_at,
            'property' => [
                'id' => $purchase->property->id,
                'title' => $purchase->property->title,
                'price' => $purchase->property->price,
                'property_state' => $purchase->property->property_state,
                'status' => $purchase->property->status,
            ],
            'escrow' => $purchase->escrow ? [
                'id' => $purchase->escrow->id,
                'status' => $purchase->escrow->status,
                'amount' => $purchase->escrow->amount,
                'locked_at' => $purchase->escrow->locked_at,
                'scheduled_release_at' => $purchase->escrow->scheduled_release_at,
                'released_at' => $purchase->escrow->released_at,
            ] : null,
            'seller' => [
                'id' => $purchase->seller->id,
                'first_name' => $purchase->seller->first_name,
                'last_name' => $purchase->seller->last_name,
                'email' => $purchase->seller->email,
            ]
        ];

        return $this->success('Purchase found.', [
            'purchase' => $transformedPurchase
        ]);

    } catch (\Throwable $e) {
        Log::error('getUserPurchaseForProperty error: ' . $e->getMessage(), [
            'user_id' => $user->id,
            'property_id' => $propertyId,
            'trace' => $e->getTraceAsString()
        ]);
        return $this->error('Failed to retrieve purchase.', 500);
    }
}

}