<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\PaymobPaymentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class PaymentController extends Controller
{
    protected PaymobPaymentService $paymob;

    public function __construct(PaymobPaymentService $paymob)
    {
        $this->paymob = $paymob;
    }

    // -------------------- TOP UP WALLET --------------------
    public function topUpWallet(Request $request)
    {
        $request->validate([
            'amount' => 'required|numeric|min:10',
        ]);

        $user = $request->user();

        $paymentKey = $this->paymob->createPaymentKey([
            'amount_cents'   => intval($request->amount * 100),
            'currency'       => 'EGP',
            'user'           => $user,
        ]);

        return response()->json([
            'success'     => true,
            'payment_key' => $paymentKey,
            'iframe_url'  => env('PAYMOB_IFRAME_URL') . '?payment_token=' . $paymentKey,
        ]);
    }

    // -------------------- PAYMOB CALLBACK --------------------
public function callBack(Request $request): array
{
    $response = $request->all();
    \Log::info('Paymob callback received', $response);

    $obj = $response['obj'] ?? $response['transaction'] ?? $response;

    // ✅ Skip HMAC validation completely

    // Ensure success
    if (!isset($obj['success']) || $obj['success'] !== true) {
        return ['success' => false, 'message' => 'Payment failed'];
    }

    $amount = ($obj['amount_cents'] ?? 0) / 100;
    $merchantOrderId = $obj['order']['merchant_order_id'] ?? $obj['merchant_order_id'] ?? null;

    if (!$merchantOrderId) {
        return ['success' => false, 'message' => 'Missing merchant_order_id'];
    }

    // ----------------------------
    // WALLET FLOW
    // ----------------------------
    if (str_starts_with($merchantOrderId, 'wallet-')) {
        $userId = intval(explode('-', $merchantOrderId)[1] ?? 0);
        $user = User::find($userId);
        if (!$user) {
            return ['success' => false, 'message' => 'User not found'];
        }

        DB::transaction(function () use ($user, $amount, $obj) {
            $wallet = Wallet::lockForUpdate()->firstOrCreate(
                ['user_id' => $user->id],
                ['balance' => 0]
            );

            $before = $wallet->balance;
            $wallet->increment('balance', $amount);

            WalletTransaction::create([
                'wallet_id'      => $wallet->id,
                'amount'         => $amount,
                'type'           => 'topup',
                'ref_id'         => $obj['id'] ?? null,
                'ref_type'       => 'paymob',
                'description'    => 'Wallet top-up via Paymob',
                'balance_before' => $before,
                'balance_after'  => $wallet->balance,
            ]);
        });

        return ['success' => true, 'message' => 'Wallet topped up successfully', 'amount' => $amount];
    }

    // ----------------------------
    // PROPERTY PURCHASE FLOW (BUY)
    // ----------------------------
    if (str_starts_with($merchantOrderId, 'buy-')) {
        $parts = explode('-', $merchantOrderId);
        $propertyId = intval($parts[1] ?? 0);
        $userId     = intval($parts[2] ?? 0);

        $purchase = PropertyPurchase::where('property_id', $propertyId)
            ->where('buyer_id', $userId)
            ->where('status', 'pending_payment')
            ->latest()
            ->first();

        if (!$purchase) {
            return ['success' => false, 'message' => 'Purchase not found or already processed'];
        }

        DB::transaction(function () use ($purchase, $obj) {
            $purchase->update([
                'status'          => 'confirmed',
                'transaction_ref' => $obj['id'] ?? $purchase->transaction_ref,
                'metadata'        => array_merge($purchase->metadata ?? [], [
                    'paymob_txn' => $obj,
                ]),
            ]);

            // Escrow
            \App\Models\PropertyEscrow::create([
                'property_purchase_id' => $purchase->id,
                'property_id'          => $purchase->property_id,
                'buyer_id'             => $purchase->buyer_id,
                'seller_id'            => $purchase->seller_id,
                'amount'               => $purchase->amount,
                'status'               => 'locked',
                'locked_at'            => now(),
                'scheduled_release_at' => now()->addHours(24),
            ]);

            // Lock property
            $purchase->property->update([
                'property_state'   => 'Invalid',
                'pending_buyer_id' => $purchase->buyer_id,
            ]);

            // Notifications
            try {
                \Notification::send($purchase->buyer, new \App\Notifications\PropertyPurchaseInitiated($purchase));
                \Notification::send($purchase->seller, new \App\Notifications\PurchaseInitiatedSeller($purchase));
            } catch (\Exception $e) {
                \Log::warning('Notification failed on Paymob callback', ['error' => $e->getMessage()]);
            }
        });

        return ['success' => true, 'message' => 'Property purchase confirmed', 'property_purchase_id' => $purchase->id];
    }

    // ----------------------------
    // RENT REQUEST FLOW
    // ----------------------------
    if (str_starts_with($merchantOrderId, 'rent-')) {
        $parts = explode('-', $merchantOrderId);
        $rentRequestId = intval($parts[1] ?? 0);
        $userId        = intval($parts[2] ?? 0);

        $rentRequest = \App\Models\RentRequest::where('id', $rentRequestId)
            ->where('user_id', $userId)
            ->lockForUpdate()
            ->first();

        if (!$rentRequest) {
            return ['success' => false, 'message' => 'Rent request not found'];
        }

        if ($rentRequest->status !== 'confirmed') {
            return ['success' => false, 'message' => 'Rent request not in confirmed state'];
        }

        $property = $rentRequest->property;
        $checkIn  = \Carbon\Carbon::parse($rentRequest->check_in);
        $checkOut = \Carbon\Carbon::parse($rentRequest->check_out);
        $days     = max(1, $checkIn->diffInDays($checkOut));

        $pricePerNight = $property->price_per_night ?? $property->price ?? $property->daily_rent;
        $rentAmount    = $pricePerNight * $days;
        $depositAmount = $pricePerNight;
        $totalAmount   = $rentAmount + $depositAmount;

        DB::transaction(function () use ($rentRequest, $rentAmount, $depositAmount, $totalAmount, $obj) {
            // Update rent request
            $rentRequest->update([
                'status'          => 'paid',
                'transaction_ref' => $obj['id'] ?? null,
            ]);

            // Escrow
            \App\Models\EscrowBalance::create([
                'rent_request_id' => $rentRequest->id,
                'user_id'         => $rentRequest->user_id,
                'rent_amount'     => $rentAmount,
                'deposit_amount'  => $depositAmount,
                'total_amount'    => $totalAmount,
                'status'          => 'locked',
                'locked_at'       => now(),
            ]);

            // Schedule release
            \App\Jobs\ReleaseRentJob::dispatch($rentRequest->id)
                ->delay($checkIn->addDay());

            // Notifications
            try {
                \Notification::send($rentRequest->user, new \App\Notifications\RentPaid($rentRequest));
                \Notification::send($rentRequest->property->owner, new \App\Notifications\RentPaymentReceived($rentRequest));
            } catch (\Exception $e) {
                \Log::warning('Rent notifications failed', ['error' => $e->getMessage()]);
            }
        });

        return [
            'success'        => true,
            'message'        => 'Rent request paid successfully',
            'rent_request_id'=> $rentRequest->id,
            'amount'         => $totalAmount,
        ];
    }

    // ----------------------------
    // UNKNOWN FLOW
    // ----------------------------
    return ['success' => false, 'message' => 'Unknown payment flow'];
}
}