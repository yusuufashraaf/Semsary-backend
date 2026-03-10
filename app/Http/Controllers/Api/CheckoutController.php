<?php

namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller;
use App\Models\Checkout;
use App\Models\RentRequest;
use App\Models\Purchase;
use App\Models\EscrowBalance;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use App\Models\Property;
use App\Models\UserNotification;
use App\Enums\NotificationPurpose;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Carbon\Carbon;
use Exception;

class CheckoutController extends Controller
{
    public  function success($message, $data = null, $status = 200)
    {
        $payload = ['success' => true, 'message' => $message];
        if (!is_null($data))
            $payload['data'] = $data;
        return response()->json($payload, $status);
    }

    public  function error($message, $status = 422, $details = null)
    {
        $payload = [
            'success' => false,
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
            $entityId = $notificationData['checkout_id'] ?? $notificationData['rent_request_id'] ?? $notificationData['id'] ?? null;
            $message = $notificationData['message'] ?? 'New notification';

            Log::info('Inserting checkout notification', [
                'recipient' => $recipient->id,
                'purpose'   => $purpose->value,
            ]);

            // Create UserNotification record
            $notification = UserNotification::create([
                'user_id' => $recipient->id,
                'sender_id' => $senderId,
                'entity_id' => $entityId,
                'purpose' => $purpose,
                'title' => $purpose->label(),
                'message' => $message,
                'is_read' => false,
            ]);

            Log::info('Checkout notification row created', [
                'id' => optional($notification)->id
            ]);
        } catch (Exception $e) {
            Log::warning('Failed to create checkout UserNotification', [
                'error' => $e->getMessage(),
                'recipient_id' => $recipient->id,
            ]);
        }
    }

    /**
     * MAIN CHECKOUT ENDPOINT - Handles all checkout actions
     */
    public function processCheckout(Request $request, $rentRequestId)
    {
        $validator = \Validator::make($request->all(), [
            'action' => 'required|in:request_checkout,agent_decision,owner_confirm,owner_reject,admin_override',
            'reason' => 'nullable|string|max:500',
            'deposit_return_percent' => 'nullable|numeric|min:0|max:100',
            'rent_returned' => 'nullable|boolean',
            'notes' => 'nullable|string|max:1000',
            'damage_notes' => 'nullable|string|max:1000',
            'admin_note' => 'nullable|string|max:1000',
        ]);

        if ($validator->fails()) {
            return $this->error('Validation failed.', 422, $validator->errors());
        }

        $user = $request->user();
        if (!$user || $user->status !== 'active') {
            return $this->error('Authentication required and account must be active.', 401);
        }

        $action = $request->input('action');

        try {
            return DB::transaction(function () use ($rentRequestId, $user, $request, $action) {
                $rentRequest = RentRequest::lockForUpdate()->findOrFail($rentRequestId);

                switch ($action) {
                    case 'request_checkout':
                        return $this->handleUserCheckout($rentRequest, $user, $request);

                    case 'agent_decision':
    return $this->handleAgentDecisionInternal($rentRequest, $user, $request);

                    case 'owner_confirm':
                        return $this->handleOwnerConfirm($rentRequest, $user, $request);

                    case 'owner_reject':
                        return $this->handleOwnerReject($rentRequest, $user, $request);

                    case 'admin_override':
                        return $this->handleAdminOverride($rentRequest, $user, $request);

                    default:
                        return $this->error('Invalid action specified.', 422);
                }
            });
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return $this->error('Rental request not found.', 404);
        } catch (\Exception $e) {
    \Log::error('Checkout error: '.$e->getMessage(), [
        'trace' => $e->getTraceAsString(),
        'rent_request_id' => $rentRequestId,
        'user_id' => optional($request->user())->id,
        'action' => $request->action ?? null,
    ]);

    // during dev, return real error
    return response()->json([
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ], 500);
}

    }

    /**
     * USER REQUESTS CHECKOUT - Main entry point
     */
    public function handleUserCheckout($rentRequest, $user, $request)
    {
        // Only renter can request checkout
        if ($rentRequest->user_id !== $user->id) {
            return $this->error('You can only checkout your own rentals.', 403);
        }

        if ($rentRequest->status !== 'paid') {
            return $this->error('Only paid rentals can be checked out.', 422);
        }

        // Prevent duplicate checkouts
        $existingCheckout = Checkout::where('rent_request_id', $rentRequest->id)->first();
        if ($existingCheckout) {
            return $this->success('Checkout already exists.', [
                'checkout' => $existingCheckout,
                'can_act' => $this->getUserActions($existingCheckout, $user),
            ]);
        }

        // Verify escrow balance exists
        $escrow = EscrowBalance::where('rent_request_id', $rentRequest->id)
            ->where('status', 'locked')
            ->first();

        if (!$escrow) {
            return $this->error('No escrow balance found for this rental.', 422);
        }

        // Determine checkout type based on timing
        $checkIn = Carbon::parse($rentRequest->check_in);
        $checkOut = Carbon::parse($rentRequest->check_out);
        $now = Carbon::now();
        $oneDayAfterCheckIn = $checkIn->copy()->addDay();

        $checkoutType = 'after_1_day'; // Default
        $ownerConfirmationNeeded = 'pending';

        if ($now->lt($checkIn)) {
            $checkoutType = 'before_checkin';
            $ownerConfirmationNeeded = 'not_required';
        } elseif ($now->lte($oneDayAfterCheckIn)) {
            // "Within 1 day" (cancellation within 24h of check-in)
            // Owner is NOT involved here; agent must decide.
            $checkoutType = 'within_1_day';
            $ownerConfirmationNeeded = 'not_required';
        } elseif ($checkIn->diffInDays($checkOut) >= 30) {
            $checkoutType = 'monthly_mid_contract';
            $ownerConfirmationNeeded = 'pending';
        }

        // Create checkout record
        $checkout = Checkout::create([
            'rent_request_id' => $rentRequest->id,
            'requester_id' => $user->id,   
            'requested_at' => $now,
            'status' => 'pending',
            'type' => $checkoutType,
            'reason' => $request->input('reason'),
            'owner_confirmation' => $ownerConfirmationNeeded,
        ]);

        // Auto-process BEFORE CHECK-IN (rent lost, deposit refunded)
        if ($checkoutType === 'before_checkin') {
            $checkout->update([
                'status' => 'auto_confirmed',
                'deposit_return_percent' => 100.00,
                'agent_decision' => [
                    'deposit_return_percent' => 100,
                    'rent_returned' => false,
                    'notes' => 'Before check-in cancellation - rent forfeited, deposit fully refunded',
                    'decided_by' => 'system',
                    'decided_at' => Carbon::now()->toISOString(),
                ],
                'processed_at' => Carbon::now(),
            ]);

            return $this->finalizeCheckout($checkout);
        }

        // For WITHIN_1_DAY -> owner not involved, agent must decide
        if ($checkoutType === 'within_1_day') {
            // Keep status 'pending', owner_confirmation 'not_required' (already set)
            // Notify agents for decision (agent has to call agent_decision)
            $this->sendNotifications($checkout, 'requested');
            Log::info('Checkout requested (within 1 day) awaiting agent decision', [
                'checkout_id' => $checkout->id,
                'rent_request_id' => $rentRequest->id,
                'user_id' => $user->id,
                'type' => $checkoutType,
            ]);

            return $this->success('Checkout requested. Awaiting agent decision.', [
                'checkout' => $checkout->fresh(),
                'can_act' => $this->getUserActions($checkout, $user),
                'message' => $this->getStatusMessage($checkout),
            ], 201);
        }

        // For AFTER_1_DAY and MONTHLY -> owner confirmation required
        $this->sendNotifications($checkout, 'requested');

        Log::info('Checkout requested', [
            'checkout_id' => $checkout->id,
            'rent_request_id' => $rentRequest->id,
            'user_id' => $user->id,
            'type' => $checkoutType,
        ]);

        return $this->success('Checkout requested successfully.', [
            'checkout' => $checkout->fresh(),
            'can_act' => $this->getUserActions($checkout, $user),
            'message' => $this->getStatusMessage($checkout),
        ], 201);
    }

    /**
     * AGENT MAKES DECISION - For within_1_day, OR when owner_rejected
     */
/**
 * AGENT MAKES DECISION - Direct API endpoint
 */
public function handleAgentDecision(Request $request, $id)
{
    $user = Auth::user();

    if (!in_array($user->role, ['admin', 'agent'])) {
        return $this->error('Only admins or agents can make checkout decisions.', 403);
    }

    try {
        return DB::transaction(function () use ($id, $user, $request) {
            $checkout = Checkout::where('id', $id)->lockForUpdate()->firstOrFail();
            
            return $this->processAgentDecisionLogic($checkout, $user, $request);
        });
    } catch (\Exception $e) {
        Log::error('Agent decision error: ' . $e->getMessage());
        return $this->error('Failed to process agent decision.', 500);
    }
}

/**
 * AGENT MAKES DECISION - Internal call from processCheckout
 */
private function handleAgentDecisionInternal($rentRequest, $user, $request)
{
    $checkout = Checkout::where('rent_request_id', $rentRequest->id)
        ->lockForUpdate()
        ->first();

    if (!$checkout) {
        return $this->error('No checkout found for this rental.', 404);
    }

    return $this->processAgentDecisionLogic($checkout, $user, $request);
}

/**
 * Shared logic for agent decision processing
 */
private function processAgentDecisionLogic($checkout, $user, $request)
{
    if (!in_array($checkout->owner_confirmation, ['not_required', 'rejected'])) {
        return $this->error('Agent decision is not allowed at this stage. Owner must confirm/reject first.', 422);
    }

    if ($checkout->status !== 'pending') {
        return $this->error('Checkout is no longer pending.', 422);
    }

    $depositReturnPercent = $request->input('deposit_return_percent', 0);
    $rentReturned = $request->input('rent_returned', false);
    $notes = $request->input('notes');

    $checkout->update([
        'deposit_return_percent' => $depositReturnPercent,
        'agent_notes' => $notes,
        'agent_decision' => [
            'deposit_return_percent' => $depositReturnPercent,
            'rent_returned' => $rentReturned,
            'notes' => $notes,
            'decided_by' => $user->id,
            'decided_at' => now()->toISOString(),
        ],
        'status' => 'confirmed',
        'processed_at' => now(),
    ]);

    Log::info('Agent decision made', [
        'checkout_id' => $checkout->id,
        'agent_id' => $user->id,
        'deposit_return_percent' => $depositReturnPercent,
        'rent_returned' => $rentReturned,
    ]);

    return $this->finalizeCheckout($checkout);
}
    /**
     * OWNER CONFIRMS - For after_1_day, monthly_mid_contract
     * Owner confirms means deposit goes back to renter (100%)
     */
    public  function handleOwnerConfirm($rentRequest, $user, $request)
    {
        if ($rentRequest->property->owner_id !== $user->id && !$user->hasRole('admin')) {
            return $this->error('Only property owner or admin can confirm checkout.', 403);
        }

        $checkout = Checkout::where('rent_request_id', $rentRequest->id)
            ->lockForUpdate()
            ->first();

        if (!$checkout) {
            return $this->error('No checkout found for this rental.', 404);
        }

        if ($checkout->owner_confirmation !== 'pending') {
            return $this->error('Owner confirmation is not pending.', 422);
        }

        $damageNotes = $request->input('damage_notes');

        // Owner confirms → deposit returned to renter (100%)
        $checkout->update([
            'owner_confirmation' => 'confirmed',
            'owner_notes' => $damageNotes,
            'status' => 'confirmed',
            'deposit_return_percent' => 100.00,
            'processed_at' => Carbon::now(),
        ]);

        Log::info('Owner confirmed checkout', [
            'checkout_id' => $checkout->id,
            'owner_id' => $user->id,
        ]);

        return $this->finalizeCheckout($checkout);
    }

    /**
     * OWNER REJECTS - If damages found -> escalate to agent for decision
     */
    public  function handleOwnerReject($rentRequest, $user, $request)
    {
        if ($rentRequest->property->owner_id !== $user->id && !$user->hasRole('admin')) {
            return $this->error('Only property owner or admin can reject checkout.', 403);
        }

        $checkout = Checkout::where('rent_request_id', $rentRequest->id)
            ->lockForUpdate()
            ->first();

        if (!$checkout) {
            return $this->error('No checkout found for this rental.', 404);
        }

        if ($checkout->owner_confirmation !== 'pending') {
            return $this->error('Owner confirmation is not pending.', 422);
        }

        $damageNotes = $request->input('damage_notes');

        // Mark owner as rejected, DO NOT mark final status as rejected.
        // Keep overall checkout status = pending so agent can act.
        $checkout->update([
            'owner_confirmation' => 'rejected',
            'owner_notes' => $damageNotes,
            'status' => 'pending',
        ]);

        // Notify agents for review (agent will call agent_decision)
        $this->sendNotifications($checkout, 'owner_rejected');

        Log::info('Owner rejected checkout (escalated to agent)', [
            'checkout_id' => $checkout->id,
            'owner_id' => $user->id,
            'damage_notes' => $damageNotes,
        ]);

        return $this->success('Owner rejected checkout. Escalated to agent for decision.', [
            'checkout' => $checkout->fresh(),
            'can_act' => $this->getUserActions($checkout, $user),
            'message' => $this->getStatusMessage($checkout),
        ]);
    }

    /**
     * ADMIN OVERRIDE - Final decision on disputed checkouts
     */
    public  function handleAdminOverride($rentRequest, $user, $request)
    {
        if (!$user->hasRole('admin')) {
            return $this->error('Only admins can override checkout decisions.', 403);
        }

        $checkout = Checkout::where('rent_request_id', $rentRequest->id)
            ->lockForUpdate()
            ->first();

        if (!$checkout) {
            return $this->error('No checkout found for this rental.', 404);
        }

        $depositReturnPercent = $request->input('deposit_return_percent', 0);
        $rentReturned = $request->input('rent_returned', false);
        $adminNote = $request->input('admin_note');

        $checkout->update([
            'status' => 'confirmed',
            'deposit_return_percent' => $depositReturnPercent,
            'agent_decision' => [
                'deposit_return_percent' => $depositReturnPercent,
                'rent_returned' => $rentReturned,
                'notes' => 'Admin override: ' . $adminNote,
                'decided_by' => $user->id,
                'decided_at' => Carbon::now()->toISOString(),
                'override' => true,
            ],
            'admin_note' => $adminNote,
            'processed_at' => Carbon::now(),
        ]);

        Log::info('Admin override applied', [
            'checkout_id' => $checkout->id,
            'admin_id' => $user->id,
            'deposit_return_percent' => $depositReturnPercent,
        ]);

        return $this->finalizeCheckout($checkout);
    }

    /**
     * Get available actions for current user based on checkout status
     */
    public  function getUserActions($checkout, $user)
    {
        $actions = [];
        $isAdmin = $user->hasRole('admin');
        $isAgent = $user->hasRole('agent');
        $isOwner = $checkout->rentRequest->property->owner_id === $user->id;
        $isRenter = $checkout->rentRequest->user_id === $user->id;

        switch ($checkout->status) {
            case 'pending':
                // Agent can make decision if owner_confirmation is 'not_required' (within_1_day)
                // OR if owner explicitly rejected (owner_confirmation === 'rejected')
                if (($isAdmin || $isAgent) && !$checkout->agent_decision && in_array($checkout->owner_confirmation, ['not_required', 'rejected'])) {
                    $actions[] = 'agent_decision';
                }

                // Owner can confirm/reject if owner_confirmation is pending
                if ($isOwner && $checkout->owner_confirmation === 'pending') {
                    $actions[] = 'owner_confirm';
                    $actions[] = 'owner_reject';
                }
                break;

            case 'rejected':
                // kept for backward compatibility — admin can override
                if ($isAdmin) {
                    $actions[] = 'admin_override';
                }
                break;

            case 'confirmed':
            case 'auto_confirmed':
                // No actions available - checkout is complete
                break;
        }

        return $actions;
    }

    /**
     * Get human-readable status message
     */
    public  function getStatusMessage($checkout)
    {
        switch ($checkout->status) {
            case 'pending':
                if (!$checkout->agent_decision && $checkout->owner_confirmation === 'not_required') {
                    return 'Awaiting agent decision on refund amount.';
                } elseif ($checkout->owner_confirmation === 'pending') {
                    return 'Awaiting owner confirmation or rejection.';
                } elseif ($checkout->owner_confirmation === 'rejected' && !$checkout->agent_decision) {
                    return 'Owner reported damages. Awaiting agent review.';
                } elseif ($checkout->agent_decision && $checkout->owner_confirmation === 'rejected') {
                    return 'Agent decided. Finalizing...';
                }
                return 'Processing checkout...';

            case 'rejected':
                return 'Owner reported damages. Awaiting admin/agent decision.';

            case 'confirmed':
                return 'Checkout confirmed. Refund/payout processing in progress.';

            case 'auto_confirmed':
                return 'Checkout auto-confirmed due to owner inactivity. Refund processed.';

            default:
                return 'Unknown status.';
        }
    }

    /**
     * Send notifications based on checkout events
     */
    public  function sendNotifications($checkout, $event)
    {
        try {
            $rentRequest = $checkout->rentRequest;

            switch ($event) {
                case 'requested':
                    // Notify agent/admin for review
                    $adminUsers = \App\Models\User::whereHas('roles', function($q) {
                        $q->whereIn('name', ['admin', 'agent']);
                    })->get();

                    if ($adminUsers->count() > 0) {
                        $notification = new \App\Notifications\CheckoutRequested($checkout);
                        Notification::send($adminUsers, $notification);
                        
                        // Create UserNotification for each admin/agent
                        foreach ($adminUsers as $adminUser) {
                            $this->createUserNotificationFromWebsocketData(
                                $adminUser,
                                $notification,
                                NotificationPurpose::CHECKOUT_REQUESTED,
                                $checkout->requester_id
                            );
                        }
                    }
                    break;

                case 'agent_decided':
                    // Notify owner if confirmation needed (rare: if agent decision should notify owner)
                    if ($checkout->owner_confirmation === 'pending') {
                        $notification = new \App\Notifications\CheckoutAwaitingOwnerConfirmation($checkout);
                        Notification::send($rentRequest->property->owner, $notification);
                        
                        // Create UserNotification from websocket data
                        $this->createUserNotificationFromWebsocketData(
                            $rentRequest->property->owner,
                            $notification,
                            NotificationPurpose::CHECKOUT_AWAITING_OWNER_CONFIRMATION,
                            null
                        );
                    }
                    break;

                case 'owner_rejected':
                    // Notify agents for dispute resolution
                    $agentUsers = \App\Models\User::whereHas('roles', function($q) {
                        $q->where('name', 'agent');
                    })->get();

                    if ($agentUsers->count() > 0) {
                        $notification = new \App\Notifications\CheckoutDispute($checkout);
                        Notification::send($agentUsers, $notification);
                        
                        // Create UserNotification for each agent
                        foreach ($agentUsers as $agentUser) {
                            $this->createUserNotificationFromWebsocketData(
                                $agentUser,
                                $notification,
                                NotificationPurpose::CHECKOUT_DISPUTE,
                                $rentRequest->property->owner_id
                            );
                        }
                    }
                    break;

                case 'completed':
                    // Notify both parties of completion
                    $userNotification = new \App\Notifications\CheckoutCompleted($checkout);
                    Notification::send($rentRequest->user, $userNotification);
                    
                    // Create UserNotification from websocket data for user
                    $this->createUserNotificationFromWebsocketData(
                        $rentRequest->user,
                        $userNotification,
                        NotificationPurpose::CHECKOUT_COMPLETED,
                        null
                    );

                    $ownerNotification = new \App\Notifications\CheckoutCompleted($checkout);
                    Notification::send($rentRequest->property->owner, $ownerNotification);
                    
                    // Create UserNotification from websocket data for owner
                    $this->createUserNotificationFromWebsocketData(
                        $rentRequest->property->owner,
                        $ownerNotification,
                        NotificationPurpose::CHECKOUT_COMPLETED,
                        null
                    );
                    break;
            }
        } catch (Exception $e) {
            Log::warning('Failed to send checkout notifications', [
                'error' => $e->getMessage(),
                'checkout_id' => $checkout->id,
                'event' => $event,
            ]);
        }
    }

    /**
     * Finalize checkout - process refunds and payouts
     */
    public  function finalizeCheckout($checkout)
    {
        return DB::transaction(function () use ($checkout) {
            $rentRequest = $checkout->rentRequest()->lockForUpdate()->first();
            $escrow = EscrowBalance::where('rent_request_id', $rentRequest->id)
                ->lockForUpdate()
                ->first();

            if (!$escrow || $escrow->status !== 'locked') {
                throw new Exception('Invalid escrow state for checkout finalization');
            }

            // Calculate refund and payout amounts
            $depositReturnPercent = $checkout->deposit_return_percent ?? 0;
            // Ensure numeric string, use bc math to keep precision
            $refundAmount = bcmul($escrow->deposit_amount, bcdiv((string)$depositReturnPercent, '100', 4), 2);
            $depositToOwner = bcsub($escrow->deposit_amount, $refundAmount, 2);

            $agentDecision = $checkout->agent_decision ?? [];
            $rentReturned = $agentDecision['rent_returned'] ?? false;
            $rentToOwner = $rentReturned ? 0 : $escrow->rent_amount;
            $rentRefund = $rentReturned ? $escrow->rent_amount : 0;

            $totalRefund = bcadd($refundAmount, $rentRefund, 2);
            $totalPayout = bcadd($depositToOwner, $rentToOwner, 2);

            $transactionRef = Str::uuid()->toString();

            // Process refunds and payouts
            if ($totalRefund > 0) {
                $this->processRefund($checkout, $rentRequest, $totalRefund, $refundAmount, $rentRefund, $transactionRef, $depositReturnPercent);
            }

            if ($totalPayout > 0) {
                $this->processPayout($checkout, $rentRequest, $totalPayout, $depositToOwner, $rentToOwner, $transactionRef, $depositReturnPercent);
            }

            // Release escrow and update status
            $escrow->update([
                'status' => 'released_to_owner',
                'released_at' => Carbon::now(),
            ]);

            $rentRequest->update(['status' => 'completed']);

            $checkout->update([
                'transaction_ref' => $transactionRef,
                'final_refund_amount' => $totalRefund,
                'final_payout_amount' => $totalPayout,
            ]);

            // Send completion notifications
            $this->sendNotifications($checkout, 'completed');

            Log::info('Checkout finalized', [
                'checkout_id' => $checkout->id,
                'refund_amount' => $totalRefund,
                'payout_amount' => $totalPayout,
                'transaction_ref' => $transactionRef,
            ]);

            return $this->success('Checkout completed successfully.', [
                'checkout' => $checkout->fresh(),
                'refund_amount' => $totalRefund,
                'payout_amount' => $totalPayout,
                'transaction_ref' => $transactionRef,
                'can_act' => [], // No more actions available
                'message' => 'Checkout completed. Funds have been distributed.',
            ]);
        });
    }

    // Helper methods for refund/payout processing
    public  function processRefund($checkout, $rentRequest, $totalRefund, $refundAmount, $rentRefund, $transactionRef, $depositReturnPercent)
    {
        $refundPurchase = Purchase::create([
            'rent_request_id' => $rentRequest->id,
            'user_id' => $rentRequest->user_id,
            'property_id' => $rentRequest->property_id,
            'amount' => $totalRefund,
            'payment_type' => 'refund',
            'status' => 'successful',
            'payment_gateway' => 'Wallet',
            'transaction_ref' => $transactionRef,
            'idempotency_key' => Str::uuid()->toString(),
            'metadata' => [
                'checkout_id' => $checkout->id,
                'deposit_refund' => $refundAmount,
                'rent_refund' => $rentRefund,
                'deposit_return_percent' => $depositReturnPercent,
            ],
        ]);

        $renterWallet = Wallet::lockForUpdate()->firstOrCreate([
            'user_id' => $rentRequest->user_id,
        ], ['balance' => 0]);

        $balanceBefore = $renterWallet->balance;
        $renterWallet->balance = bcadd($renterWallet->balance, $totalRefund, 2);
        $renterWallet->save();

        WalletTransaction::create([
            'wallet_id' => $renterWallet->id,
            'amount' => $totalRefund,
            'type' => 'refund',
            'ref_id' => $checkout->id,
            'ref_type' => 'checkout',
            'description' => 'Checkout refund - deposit: ' . $refundAmount . ', rent: ' . $rentRefund,
            'balance_before' => $balanceBefore,
            'balance_after' => $renterWallet->balance,
        ]);

        // persist relation
        $checkout->refund_purchase_id = $refundPurchase->id;
        $checkout->save();

        // Send refund notification
        try {
            $notification = new \App\Notifications\CheckoutRefundProcessed($checkout, $totalRefund);
            Notification::send($rentRequest->user, $notification);
            
            // Create UserNotification from websocket data
            $this->createUserNotificationFromWebsocketData(
                $rentRequest->user,
                $notification,
                NotificationPurpose::CHECKOUT_REFUND_PROCESSED,
                null
            );
        } catch (Exception $e) {
            Log::warning('Failed to send refund notification', [
                'error' => $e->getMessage(),
                'checkout_id' => $checkout->id
            ]);
        }
    }

    public  function processPayout($checkout, $rentRequest, $totalPayout, $depositToOwner, $rentToOwner, $transactionRef, $depositReturnPercent)
    {
        $payoutPurchase = Purchase::create([
            'rent_request_id' => $rentRequest->id,
            'user_id' => $rentRequest->property->owner_id,
            'property_id' => $rentRequest->property_id,
            'amount' => $totalPayout,
            'payment_type' => 'payout',
            'status' => 'successful',
            'payment_gateway' => 'Wallet',
            'transaction_ref' => $transactionRef,
            'idempotency_key' => Str::uuid()->toString(),
            'metadata' => [
                'checkout_id' => $checkout->id,
                'deposit_payout' => $depositToOwner,
                'rent_payout' => $rentToOwner,
                'deposit_return_percent' => $depositReturnPercent,
            ],
        ]);

        $ownerWallet = Wallet::lockForUpdate()->firstOrCreate([
            'user_id' => $rentRequest->property->owner_id,
        ], ['balance' => 0]);

        $balanceBefore = $ownerWallet->balance;
        $ownerWallet->balance = bcadd($ownerWallet->balance, $totalPayout, 2);
        $ownerWallet->save();

        WalletTransaction::create([
            'wallet_id' => $ownerWallet->id,
            'amount' => $totalPayout,
            'type' => 'payout',
            'ref_id' => $checkout->id,
            'ref_type' => 'checkout',
            'description' => 'Checkout payout - deposit: ' . $depositToOwner . ', rent: ' . $rentToOwner,
            'balance_before' => $balanceBefore,
            'balance_after' => $ownerWallet->balance,
        ]);

        // persist relation
        $checkout->payout_purchase_id = $payoutPurchase->id;
        $checkout->save();

        // Send payout notification
        try {
            $notification = new \App\Notifications\CheckoutPayoutProcessed($checkout, $totalPayout);
            Notification::send($rentRequest->property->owner, $notification);
            
            // Create UserNotification from websocket data
            $this->createUserNotificationFromWebsocketData(
                $rentRequest->property->owner,
                $notification,
                NotificationPurpose::CHECKOUT_PAYOUT_PROCESSED,
                null
            );
        } catch (Exception $e) {
            Log::warning('Failed to send payout notification', [
                'error' => $e->getMessage(),
                'checkout_id' => $checkout->id
            ]);
        }
    }

    /**
     * Auto-confirm checkouts after 72 hours of owner inactivity
     */
    public function autoConfirmExpiredCheckouts()
    {
        try {
            $expiredTime = Carbon::now()->subHours(72);

            $expiredCheckouts = Checkout::where('status', 'pending')
                ->where('owner_confirmation', 'pending')
                ->where('requested_at', '<=', $expiredTime)
                ->get();

            $confirmedCount = 0;

            foreach ($expiredCheckouts as $checkout) {
                try {
                    DB::transaction(function () use ($checkout, &$confirmedCount) {
                        $checkoutLocked = Checkout::lockForUpdate()->find($checkout->id);

                        if (!$checkoutLocked || $checkoutLocked->status !== 'pending') {
                            return;
                        }

                        // Auto-confirm -> deposit returned to renter (100%)
                        $checkoutLocked->update([
                            'status' => 'auto_confirmed',
                            'owner_confirmation' => 'auto_confirmed',
                            'processed_at' => Carbon::now(),
                            'admin_note' => 'Auto-confirmed after 72 hours of owner inactivity',
                            'deposit_return_percent' => 100.00,
                        ]);

                        $this->finalizeCheckout($checkoutLocked);
                        $confirmedCount++;

                        Log::info('Checkout auto-confirmed due to owner inactivity', [
                            'checkout_id' => $checkout->id,
                        ]);
                    });
                } catch (Exception $e) {
                    Log::error('Error auto-confirming checkout', [
                        'checkout_id' => $checkout->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            Log::info('Auto-confirm job completed', [
                'total_expired' => $expiredCheckouts->count(),
                'successfully_confirmed' => $confirmedCount,
            ]);

            return $this->success('Auto-confirm job processed.', [
                'total_expired' => $expiredCheckouts->count(),
                'confirmed' => $confirmedCount,
            ]);
        } catch (Exception $e) {
            Log::error('autoConfirmExpiredCheckouts error: ' . $e->getMessage());
            return $this->error('Auto-confirm job failed.', 500);
        }
    }

    /**
     * Get checkout status for a rental
     */
    public function getCheckoutStatus(Request $request, $rentRequestId)
    {
        $user = $request->user();

        try {
            $rentRequest = RentRequest::findOrFail($rentRequestId);

            // Check access
            $isRenter = $rentRequest->user_id === $user->id;
            $isOwner = $rentRequest->property->owner_id === $user->id;
            $isAdmin = $user->hasRole('admin') || $user->hasRole('agent');

            if (!$isRenter && !$isOwner && !$isAdmin) {
                return $this->error('You do not have access to this rental.', 403);
            }

            $checkout = Checkout::where('rent_request_id', $rentRequestId)->first();

            if (!$checkout) {
                // No checkout yet - user can request checkout if rental is paid
                return $this->success('No checkout requested yet.', [
                    'checkout' => null,
                    'can_act' => $rentRequest->status === 'paid' && $isRenter ? ['request_checkout'] : [],
                    'message' => $rentRequest->status === 'paid' ? 'You can request checkout anytime.' : 'Rental must be paid first.',
                ]);
            }

            return $this->success('Checkout status retrieved.', [
                'checkout' => $checkout,
                'can_act' => $this->getUserActions($checkout, $user),
                'message' => $this->getStatusMessage($checkout),
            ]);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return $this->error('Rental request not found.', 404);
        } catch (Exception $e) {
            Log::error('getCheckoutStatus error: ' . $e->getMessage(), [
                'rent_request_id' => $rentRequestId,
                'user_id' => $user->id,
            ]);
            return $this->error('Failed to retrieve checkout status.', 500);
        }
    }

    /**
     * Get checkout details by checkout ID
     */
    public function getCheckoutDetails(Request $request, $checkoutId)
    {
        $user = $request->user();

        try {
            $checkout = Checkout::with([
                'rentRequest' => function ($query) {
                    $query->with([
                        'property' => function ($q) {
                            $q->select('id', 'title', 'location', 'owner_id');
                        },
                        'user' => function ($q) {
                            $q->select('id', 'first_name', 'last_name');
                        }
                    ]);
                }
            ])->findOrFail($checkoutId);

            // Check access
            $isRenter = $checkout->rentRequest->user_id === $user->id;
            $isOwner = $checkout->rentRequest->property->owner_id === $user->id;
            $isAdmin = $user->hasRole('admin') || $user->hasRole('agent');

            if (!$isRenter && !$isOwner && !$isAdmin) {
                return $this->error('You do not have access to this checkout.', 403);
            }

            // Hide sensitive information based on role
            if (!$isAdmin) {
                $checkout->makeHidden(['admin_note']);
            }

            return $this->success('Checkout details retrieved.', [
                'checkout' => $checkout,
                'can_act' => $this->getUserActions($checkout, $user),
                'message' => $this->getStatusMessage($checkout),
            ]);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return $this->error('Checkout not found.', 404);
        } catch (Exception $e) {
            Log::error('getCheckoutDetails error: ' . $e->getMessage(), [
                'checkout_id' => $checkoutId,
                'user_id' => $user->id,
            ]);
            return $this->error('Failed to retrieve checkout details.', 500);
        }
    }

    /**
     * List user's checkouts (paginated)
     */
    public function listUserCheckouts(Request $request)
    {
        $user = $request->user();
        $perPage = min((int) $request->input('per_page', 20), 50);

        try {
            // Get checkouts where user is either renter or owner
            $query = Checkout::with([
                'rentRequest' => function ($q) {
                    $q->with([
                        'property' => function ($subQ) {
                            $subQ->select('id', 'title', 'location', 'owner_id');
                        }
                    ]);
                }
            ])->whereHas('rentRequest', function ($q) use ($user) {
                $q->where('user_id', $user->id)
                  ->orWhereHas('property', function ($subQ) use ($user) {
                      $subQ->where('owner_id', $user->id);
                  });
            });

            $checkouts = $query->orderBy('created_at', 'desc')->paginate($perPage);

            return $this->success('User checkouts retrieved.', $checkouts);

        } catch (Exception $e) {
            Log::error('listUserCheckouts error: ' . $e->getMessage(), [
                'user_id' => $user->id,
            ]);
            return $this->error('Failed to retrieve checkouts.', 500);
        }
    }

    /**
     * List all checkouts for admin view
     */
   public function listAdminCheckouts(Request $request)
{
    $user = $request->user();
        Log::error('listAdminCheckouts error: ' . $user->role, [
            'user_id' => $user->id,
        ]);

    if (!$user->hasRole('admin') && !$user->hasRole('agent')) {
        return $this->error('Only admins or agents can access all checkouts.', 403);
    }

    $perPage = min((int) $request->input('per_page', 20), 50);

    try {
        $query = Checkout::with([
            'rentRequest' => function ($q) {
                $q->with([
                    'property' => function ($subQ) {
                        $subQ->select('id', 'title', 'location', 'owner_id', 'agent_id');
                    },
                    'user' => function ($subQ) {
                        $subQ->select('id', 'first_name', 'last_name', 'phone_number');
                    }
                ]);
            }
        ]);

        // If agent → only their properties' checkouts
        if ($user->hasRole('agent')) {
            $query->whereHas('rentRequest.property', function ($q) use ($user) {
                $q->where('agent_id', $user->id);
            });
        }

        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        // Filter by type
        if ($request->has('type')) {
            $query->where('type', $request->type);
        }

        // Filter by date range
        if ($request->has('from') && $request->has('to')) {
            $query->whereBetween('requested_at', [$request->from, $request->to]);
        }

        $checkouts = $query->orderBy('created_at', 'desc')->paginate($perPage);

        if ($checkouts->isEmpty()) {
            return $this->success('No checkouts found.', $checkouts);
        }

        return $this->success('Checkouts retrieved successfully.', $checkouts);

    } catch (Exception $e) {
        Log::error('listAdminCheckouts error: ' . $e->getMessage(), [
            'user_id' => $user->id,
        ]);
        return $this->error('Failed to retrieve checkouts.', 500);
    }
}


    /**
     * Get checkout statistics for dashboard
     */
    public function getCheckoutStats(Request $request)
    {
        $user = $request->user();

        try {
            // User stats (as renter)
            $renterStats = [
                'pending' => Checkout::whereHas('rentRequest', function($q) use ($user) {
                    $q->where('user_id', $user->id);
                })->where('status', 'pending')->count(),

                'confirmed' => Checkout::whereHas('rentRequest', function($q) use ($user) {
                    $q->where('user_id', $user->id);
                })->whereIn('status', ['confirmed', 'auto_confirmed'])->count(),

                'rejected' => Checkout::whereHas('rentRequest', function($q) use ($user) {
                    $q->where('user_id', $user->id);
                })->where('status', 'rejected')->count(),

                'total' => Checkout::whereHas('rentRequest', function($q) use ($user) {
                    $q->where('user_id', $user->id);
                })->count(),
            ];

            // Owner stats
            $ownerStats = [
                'pending' => Checkout::whereHas('rentRequest.property', function($q) use ($user) {
                    $q->where('owner_id', $user->id);
                })->where('status', 'pending')->count(),

                'confirmed' => Checkout::whereHas('rentRequest.property', function($q) use ($user) {
                    $q->where('owner_id', $user->id);
                })->whereIn('status', ['confirmed', 'auto_confirmed'])->count(),

                'rejected' => Checkout::whereHas('rentRequest.property', function($q) use ($user) {
                    $q->where('owner_id', $user->id);
                })->where('status', 'rejected')->count(),

                'total' => Checkout::whereHas('rentRequest.property', function($q) use ($user) {
                    $q->where('owner_id', $user->id);
                })->count(),
            ];

            return $this->success('Checkout statistics retrieved.', [
                'as_renter' => $renterStats,
                'as_owner' => $ownerStats,
            ]);

        } catch (Exception $e) {
            Log::error('getCheckoutStats error: ' . $e->getMessage(), [
                'user_id' => $user->id,
            ]);
            return $this->error('Failed to retrieve statistics.', 500);
        }
    }

    /**
     * List all transactions
     */
    public function listTransactions(Request $request)
    {
        $user = $request->user();
        $perPage = $request->get('per_page', 15);

        try {
            $query = Purchase::with([
                'rentRequest.property:id,title,location,owner_id',
                'rentRequest.user:id,first_name,last_name,phone_number'
            ]);

            // Role-based filtering
            if ($user->hasRole('admin') || $user->hasRole('agent')) {
                // Admin/agent → all transactions
            } elseif ($user->hasRole('owner')) {
                // Owner → only related to their properties
                $query->whereHas('rentRequest.property', function ($q) use ($user) {
                    $q->where('owner_id', $user->id);
                });
            } else {
                // Renter → only their transactions
                $query->where('user_id', $user->id);
            }

            // Search by phone (for admins/agents)
            if ($request->filled('phone_number')) {
                $phone = $request->phone_number;
                $query->whereHas('rentRequest.user', function ($q) use ($phone) {
                    $q->where('phone_number', 'LIKE', "%{$phone}%");
                });
            }

            // Filters (optional)
            if ($request->has('status')) {
                $query->where('status', $request->status);
            }

            if ($request->has('gateway')) {
                $query->where('payment_gateway', $request->gateway);
            }

            if ($request->has('from') && $request->has('to')) {
                $query->whereBetween('created_at', [$request->from, $request->to]);
            }

            // Paginated transactions
            $transactions = $query->orderBy('created_at', 'desc')->paginate($perPage);

            // Get user's wallet balance
            $wallet = Wallet::where('user_id', $user->id)->first();
            $walletBalance = $wallet ? $wallet->balance : 0;

            return $this->success('Transactions retrieved successfully.', [
                'wallet_balance' => $walletBalance,
                'transactions' => $transactions
            ]);

        } catch (Exception $e) {
            Log::error('listTransactions error: ' . $e->getMessage(), [
                'user_id' => $user->id,
            ]);
            return $this->error('Failed to retrieve transactions.', 500);
        }
    }
public function listCheckouts(Request $request)
{
    try {
        $user = auth()->user();

        if (!$user->isAdmin() && !$user->isAgent()) {
            return response()->json([
                'success' => false,
                'message' => 'Only admins or agents can access checkouts.'
            ], 403);
        }

        $checkouts = Checkout::with([
                'rentRequest.property.owner',
                'rentRequest.user'
            ])
            ->latest()
            ->get();

        return response()->json([
            'success' => true,
            'data' => $checkouts
        ]);

    } catch (\Exception $e) {
        \Log::error('listCheckouts failed', [
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ]);

        return response()->json([
            'success' => false,
            'message' => 'Failed to retrieve checkouts.',
            'error' => $e->getMessage()
        ], 500);
    }
}

}