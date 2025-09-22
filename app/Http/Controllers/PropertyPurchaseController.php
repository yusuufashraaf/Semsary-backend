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
            'payment_method_token' => 'nullable|string',
            'idempotency_key'      => 'nullable|string',
            'expected_total'       => 'required|numeric|min:0',
        ]);

        if ($validator->fails()) {
            return $this->error('Validation failed.', 422, $validator->errors());
        }

        $user = $request->user();
        if (!$user) return $this->error('Authentication required.', 401);
        if ($user->status !== 'active') return $this->error('Your account must be active.', 403);

        $idempotencyKey = $request->input('idempotency_key') ?? Str::uuid()->toString();
        $paymentToken   = $request->input('payment_method_token');
        $expectedTotal  = $request->input('expected_total');

        try {
            return DB::transaction(function () use ($id, $user, $idempotencyKey, $paymentToken, $expectedTotal) {
                // Idempotency
                $existingPurchase = PropertyPurchase::with('escrow')
                    ->where('idempotency_key', $idempotencyKey)
                    ->first();
                if ($existingPurchase) {
                    return $this->success('Purchase already processed.', [
                        'purchase' => $existingPurchase,
                        'escrow'   => $existingPurchase->escrow,
                        'property' => $existingPurchase->property,
                        'seller'   => $existingPurchase->seller,
                    ]);
                }

                // Property checks
                $property = Property::lockForUpdate()->find($id);
                if (!$property) return $this->error('Property not found.', 404);
                if ($property->status !== 'sale' || $property->property_state !== 'Valid') {
                    return $this->error('This property is not valid for sale.', 422);
                }

                // Seller must exist and be active
                $seller = $property->owner;
                if (!$seller || $seller->status !== 'active') {
                    return $this->error('Property owner is not active.', 422);
                }

                if ($property->owner_id === $user->id) {
                    return $this->error('You cannot buy your own property.', 422);
                }

                // Ensure buyer did not already buy this property
                $alreadyPurchased = PropertyPurchase::where('buyer_id', $user->id)
                    ->where('property_id', $property->id)
                    ->whereNotIn('status', ['cancelled', 'refunded'])
                    ->exists();
                if ($alreadyPurchased) {
                    return $this->error('You already purchased this property.', 422);
                }

                // Price validation
                $totalAmount = $property->price;
                if (abs($totalAmount - $expectedTotal) > 0.01) {
                    return $this->error('Price mismatch.', 422);
                }

                // 1. Create purchase
                $purchase = PropertyPurchase::create([
                    'property_id'          => $property->id,
                    'buyer_id'             => $user->id,
                    'seller_id'            => $property->owner_id,
                    'amount'               => $totalAmount,
                    'status'               => 'paid',
                    'payment_gateway'      => $paymentToken ? 'gateway' : 'Wallet',
                    'transaction_ref'      => Str::uuid()->toString(),
                    'idempotency_key'      => $idempotencyKey,
                    'cancellation_deadline'=> now()->addHours(24),
                    'metadata'             => [
                        'wallet_used'     => $totalAmount,
                        'gateway_charged' => 0,
                    ],
                ]);

                // 2. Create escrow
                $escrow = PropertyEscrow::create([
                    'property_purchase_id' => $purchase->id,
                    'property_id'          => $property->id,
                    'buyer_id'             => $user->id,
                    'seller_id'            => $property->owner_id,
                    'amount'               => $totalAmount,
                    'status'               => 'locked',
                    'locked_at'            => now(),
                    'scheduled_release_at' => now()->addHours(24),
                ]);

                // 3. Mark property invalid immediately
                $property->update([
                    'property_state'   => 'Invalid',
                    'pending_buyer_id' => $user->id,
                ]);

                // 4. Notifications
                try {
                    // Buyer notification
                    $buyerNotification = new \App\Notifications\PropertyPurchaseInitiated($purchase);
                    Notification::send($user, $buyerNotification);
                    $this->createUserNotificationFromWebsocketData(
                        $user,
                        $buyerNotification,
                        NotificationPurpose::PURCHASE_INITIATED,
                        $property->owner_id
                    );

                    // Seller notification
                    $sellerNotification = new \App\Notifications\PurchaseInitiatedSeller($purchase);
                    Notification::send($seller, $sellerNotification);
                    $this->createUserNotificationFromWebsocketData(
                        $seller,
                        $sellerNotification,
                        NotificationPurpose::PROPERTY_PURCHASE_REQUESTED,
                        $user->id
                    );
                } catch (Exception $e) {
                    Log::warning('Notification failed on payForOwn', ['error' => $e->getMessage()]);
                }

                return $this->success('Purchase successful.', [
                    'purchase' => $purchase,
                    'escrow'   => $escrow,
                    'property' => $property,
                    'seller'   => $seller, // return owner data
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
        if (!$escrow || $escrow->status !== 'locked') {
            return $this->error('Purchase cannot be cancelled.', 422);
        }

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
                'status'        => 'refunded_to_buyer',
                'released_at'   => now(),
                'release_reason'=> 'buyer_cancelled',
            ]);
            $purchase->update(['status' => 'cancelled']);

            try {
                $buyerNotification = new \App\Notifications\PropertyPurchaseCancelled($purchase);
                Notification::send($user, $buyerNotification);
                $this->createUserNotificationFromWebsocketData(
                    $user,
                    $buyerNotification,
                    NotificationPurpose::PURCHASE_CANCELLED,
                    $purchase->seller_id
                );

                $sellerNotification = new \App\Notifications\PurchaseCancelledByBuyer($purchase);
                Notification::send($purchase->seller, $sellerNotification);
                $this->createUserNotificationFromWebsocketData(
                    $purchase->seller,
                    $sellerNotification,
                    NotificationPurpose::PURCHASE_CANCELLED,
                    $user->id
                );
            } catch (Exception $e) {
                Log::warning('Notification failed on cancelPurchase', ['error' => $e->getMessage()]);
            }

            return $this->success('Purchase cancelled and money refunded.');
        });
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
}