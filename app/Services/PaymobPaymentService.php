<?php

namespace App\Services;

use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use App\Models\Purchase;
use App\Models\PropertyPurchase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use App\Models\PropertyEscrow;
use App\Models\EscrowBalance;
use App\Models\RentRequest;
use Illuminate\Support\Facades\Notification;

class PaymobPaymentService extends BasePaymentService
{
    protected $api_key;
    protected $integrations_id;

    public function __construct()
    {
        $this->base_url = env("PAYMOB_BASE_URL");
        $this->api_key = env("PAYMOB_API_KEY");
        $this->header = [
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
        ];
        $this->integrations_id = [env("PAYMOB_INTEGRATION_ID")]; 
    }

    // ----------------- AUTH -----------------
    protected function generateToken()
    {
        $response = $this->buildRequest('POST', '/api/auth/tokens', [
            'api_key' => $this->api_key,
        ]);

        $data = $response->getData(true);

        if (isset($data['token'])) {
            return $data['token']; 
        }

        if (isset($data['data']['token'])) {
            return $data['data']['token']; 
        }

        throw new \Exception('Failed to retrieve Paymob token: ' . json_encode($data));
    }

    // Initiate rent
    public function initiateRentPayment(Request $request, $rentRequestId): array
    {
        $user = auth()->user();
        $amount = $request->input('amount');
        $idempotencyKey = "rent-{$rentRequestId}-{$user->id}-" . time();

        $purchase = Purchase::create([
            'user_id'        => $user->id,
            'property_id'    => $request->input('property_id'),
            'rent_request_id'=> $rentRequestId,
            'amount'         => $amount,
            'payment_type'   => 'rent',
            'status'         => 'pending',
            'payment_gateway'=> 'paymob',
            'idempotency_key'=> $idempotencyKey,
            'metadata'       => ['flow' => 'rent'],
        ]);

        $paymentKey = $this->createPaymentKey([
            'amount_cents'    => intval($amount * 100),
            'currency'        => 'EGP',
            'user'            => $user,
            'idempotency_key' => $idempotencyKey,
        ]);

        return [
            'success'     => true,
            'purchase_id' => $purchase->id,
            'payment_key' => $paymentKey,
        ];
    }

    //  Initiate buy
    public function initiatePropertyPurchase(Request $request, $propertyId): array
    {
        $user = auth()->user();
        $amount = $request->input('amount');
        $idempotencyKey = "buy-{$propertyId}-{$user->id}-" . time();

        $purchase = PropertyPurchase::create([
            'property_id'    => $propertyId,
            'buyer_id'       => $user->id,
            'seller_id'      => $request->input('seller_id'),
            'amount'         => $amount,
            'status'         => 'pending',
            'payment_gateway'=> 'paymob',
            'idempotency_key'=> $idempotencyKey,
            'metadata'       => ['flow' => 'buy'],
        ]);

        $paymentKey = $this->createPaymentKey([
            'amount_cents'    => intval($amount * 100),
            'currency'        => 'EGP',
            'user'            => $user,
            'idempotency_key' => $idempotencyKey,
        ]);

        return [
            'success'               => true,
            'property_purchase_id'  => $purchase->id,
            'payment_key'           => $paymentKey,
        ];
    }

    // CALLBACK 
    /**
     * Handle callback from Paymob and process flows:
     * - wallet-: topup wallet
     * - buy-: property buy flow (existing)
     * - purchase-: property purchase flow (alternate)
     * - rent-: rent payment flow (new)
     */
    public function callBack(Request $request): array
{
    $response = $request->all();
    \Log::info('Paymob callback received (service)', $response);

    // FIX: Use response directly since Paymob sends flat data
    $obj = $response;

    // HMAC VALIDATION - COMPLETELY DISABLED
    \Log::info('HMAC validation completely disabled in PaymobPaymentService');

    // CHECK PAYMENT SUCCESS - Fixed logic
    $paymentSuccess = ($obj['success'] ?? false) === true || ($obj['success'] ?? false) === 'true';
    
    \Log::info('Payment success check', [
        'success_value' => $obj['success'] ?? 'not_set',
        'success_type' => gettype($obj['success'] ?? null),
        'payment_success' => $paymentSuccess
    ]);
    
    if (!$paymentSuccess) {
        \Log::warning('Payment was not successful', [
            'success_field' => $obj['success'] ?? 'not_set',
            'error_occured' => $obj['error_occured'] ?? 'not_set'
        ]);
        // Don't return here - we want to process failed payments too to update status
    }

    $amount          = ($obj['amount_cents'] ?? 0) / 100;
    $merchantOrderId = $obj['merchant_order_id'] ?? null;

    \Log::info('Processing callback', [
        'merchant_order_id' => $merchantOrderId,
        'amount' => $amount,
        'transaction_ref' => $obj['id'] ?? 'unknown',
        'payment_success' => $paymentSuccess
    ]);

    if (!$merchantOrderId) {
        \Log::error('Missing merchant_order_id');
        return ['success' => false, 'message' => 'Missing merchant_order_id'];
    }

    // Handle failed payments first - update status but don't process flow logic
  // Handle failed payments first - update status but don't process flow logic
if (!$paymentSuccess) {
    \Log::info('Processing failed payment', ['merchant_order_id' => $merchantOrderId]);
    
    // Update relevant records to failed status
    if (str_starts_with($merchantOrderId, 'buy-')) {
        $parts = explode('-', $merchantOrderId);
        $propertyId = intval($parts[1] ?? 0);
        $userId = intval($parts[2] ?? 0);
        
        $purchase = PropertyPurchase::where('property_id', $propertyId)
            ->where('buyer_id', $userId)
            ->where('status', 'pending')
            ->first();
            
        if ($purchase) {
            $purchase->update([
                'status' => 'failed',
                'metadata' => array_merge($purchase->metadata ?? [], [
                    'error_message' => $obj['data_message'] ?? 'Payment failed',
                    'failed_at' => now()->toISOString()
                ])
            ]);
            
            // AUTOMATICALLY Reset property status to Valid so user can try again
            $purchase->property->update([
                'status' => 'sale',
                'property_state' => 'Valid',
                'pending_buyer_id' => null,
            ]);
            
            \Log::info('Property reset to Valid after failed payment', [
                'property_id' => $propertyId,
                'purchase_id' => $purchase->id
            ]);
        }
    }
    
    return [
        'success' => false,
        'message' => 'Payment failed - property available for retry',
        'payment_success' => false
    ];
}
        // ----------------------------
        // ✅ WALLET TOPUP
        // ----------------------------
        if (str_starts_with($merchantOrderId, 'wallet-')) {
            $parts = explode('-', $merchantOrderId);
            $userId = intval($parts[1] ?? 0);
            $user   = User::find($userId);
            if (!$user) {
                \Log::error('User not found for wallet topup', ['user_id' => $userId]);
                return ['success' => false, 'message' => 'User not found'];
            }

            try {
                DB::transaction(function () use ($user, $amount, $obj) {
                    $wallet = Wallet::lockForUpdate()->firstOrCreate(['user_id' => $user->id], ['balance' => 0]);
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

                \Log::info('Wallet topup successful', ['user_id' => $user->id, 'amount' => $amount]);
                return ['success' => true, 'message' => 'Wallet topped up successfully', 'amount' => $amount];
            } catch (\Exception $e) {
                \Log::error('Wallet topup failed', ['error' => $e->getMessage()]);
                return ['success' => false, 'message' => 'Wallet topup failed'];
            }
        }

        // ----------------------------
        // ✅ PROPERTY PURCHASE (buy-/purchase-)
        // ----------------------------
        if (str_starts_with($merchantOrderId, 'buy-') || str_starts_with($merchantOrderId, 'purchase-')) {
            try {
                if (str_starts_with($merchantOrderId, 'buy-')) {
                    $parts     = explode('-', $merchantOrderId);
                    $propertyId = intval($parts[1] ?? 0);
                    $userId     = intval($parts[2] ?? 0);

                    \Log::info('Processing buy- flow', [
                        'property_id' => $propertyId,
                        'user_id' => $userId,
                        'merchant_order_id' => $merchantOrderId
                    ]);

                    $purchase = PropertyPurchase::where('property_id', $propertyId)
                        ->where('buyer_id', $userId)
                        ->whereIn('status', ['pending', 'pending_payment'])
                        ->latest()
                        ->first();

                    if (!$purchase) {
                        $purchase = PropertyPurchase::where('merchant_order_id', $merchantOrderId)->first();
                    }

                    if (!$purchase) {
                        $purchase = PropertyPurchase::where('transaction_ref', $merchantOrderId)->first();
                    }

                    if (!$purchase) {
                        \Log::error('Property purchase not found', [
                            'property_id' => $propertyId,
                            'user_id' => $userId,
                            'merchant_order_id' => $merchantOrderId
                        ]);
                        return ['success' => false, 'message' => 'Property purchase not found or already processed'];
                    }

                    \Log::info('Found purchase', [
                        'purchase_id' => $purchase->id,
                        'current_status' => $purchase->status
                    ]);

                    DB::transaction(function () use ($purchase, $obj) {
                        // Handle wallet deduction if needed
                        $walletToUse = $purchase->metadata['wallet_to_use'] ?? 0;
                        if ($walletToUse > 0) {
                            \Log::info('Processing wallet deduction', ['amount' => $walletToUse]);
                            $wallet = Wallet::lockForUpdate()->firstOrCreate(['user_id' => $purchase->buyer_id], ['balance' => 0]);
                            if ($wallet->balance >= $walletToUse) {
                                $before = $wallet->balance;
                                $wallet->decrement('balance', $walletToUse);
                                
                                WalletTransaction::create([
                                    'wallet_id'      => $wallet->id,
                                    'amount'         => -$walletToUse,
                                    'type'           => 'purchase_partial',
                                    'ref_id'         => $purchase->id,
                                    'ref_type'       => 'property_purchase',
                                    'description'    => 'Partial payment from wallet',
                                    'balance_before' => $before,
                                    'balance_after'  => $wallet->balance,
                                ]);
                            }
                        }

                        $purchase->update([
                            'status'          => 'paid',
                            'transaction_ref' => $obj['id'] ?? $purchase->transaction_ref,
                            'metadata'        => array_merge($purchase->metadata ?? [], ['paymob_txn' => $obj]),
                        ]);

                        \Log::info('Purchase updated to paid', ['purchase_id' => $purchase->id]);

                        PropertyEscrow::create([
                            'property_purchase_id' => $purchase->id,
                            'property_id'          => $purchase->property_id,
                            'buyer_id'             => $purchase->buyer_id,
                            'seller_id'            => $purchase->seller_id,
                            'amount'               => $purchase->amount,
                            'status'               => 'locked',
                            'locked_at'            => now(),
                            'scheduled_release_at' => now()->addMinute(),
                        ]);

                        $purchase->property->update([
                            'status'           => 'sale',
                            'property_state'   => 'Sold',
                            'pending_buyer_id' => null,
                        ]);

                        try {
                            \Notification::send($purchase->buyer, new \App\Notifications\PropertyPurchaseSuccessful($purchase));
                            \Notification::send($purchase->seller, new \App\Notifications\PropertyPurchaseSuccessful($purchase));
                        } catch (\Exception $e) {
                            \Log::warning('Notification failed on Paymob callback (buy)', ['error' => $e->getMessage()]);
                        }
                    });

                    \Log::info('Buy flow completed successfully', ['purchase_id' => $purchase->id]);
                    return ['success' => true, 'message' => 'Property purchase confirmed', 'purchase_id' => $purchase->id];
                }

                if (str_starts_with($merchantOrderId, 'purchase-')) {
                    $purchaseId = intval(str_replace('purchase-', '', $merchantOrderId));
                    $purchase   = PropertyPurchase::with('property', 'buyer', 'seller')->find($purchaseId);
                    if (!$purchase) {
                        \Log::error('Purchase not found for purchase- flow', ['purchase_id' => $purchaseId]);
                        return ['success' => false, 'message' => 'Purchase not found'];
                    }

                    \Log::info('Processing purchase- flow', [
                        'purchase_id' => $purchase->id,
                        'current_status' => $purchase->status
                    ]);

                    DB::transaction(function () use ($purchase, $obj) {
                        if (in_array($purchase->status, ['pending', 'pending_payment'])) {
                            $buyer  = $purchase->buyer;
                            $wallet = Wallet::lockForUpdate()->firstOrCreate(['user_id' => $buyer->id], ['balance' => 0]);
                            $walletUsed = $purchase->metadata['wallet_to_use'] ?? 0;
                            if ($walletUsed > 0) {
                                $before = $wallet->balance;
                                $wallet->decrement('balance', $walletUsed);
                                WalletTransaction::create([
                                    'wallet_id'      => $wallet->id,
                                    'amount'         => -$walletUsed,
                                    'type'           => 'purchase_partial',
                                    'ref_id'         => $purchase->id,
                                    'ref_type'       => 'property_purchase',
                                    'description'    => 'Partial payment from wallet',
                                    'balance_before' => $before,
                                    'balance_after'  => $wallet->balance,
                                ]);
                            }

                            $purchase->update([
                                'status'                => 'paid',
                                'transaction_ref'       => $obj['id'] ?? $purchase->transaction_ref,
                                'cancellation_deadline' => now()->addMinute(),
                            ]);

                            $purchase->property->update([
                                'status'           => 'sold',
                                'property_state'   => 'Sold',
                                'pending_buyer_id' => null,
                            ]);

                            PropertyEscrow::create([
                                'property_purchase_id' => $purchase->id,
                                'property_id'          => $purchase->property_id,
                                'buyer_id'             => $purchase->buyer_id,
                                'seller_id'            => $purchase->seller_id,
                                'amount'               => $purchase->amount,
                                'status'               => 'locked',
                                'locked_at'            => now(),
                                'scheduled_release_at' => now()->addMinute(),
                            ]);
                        }
                    });

                    return ['success' => true, 'message' => 'Property purchase processed (purchase-)', 'purchase_id' => $purchase->id];
                }
            } catch (\Throwable $e) {
                \Log::error('Error processing buy/purchase callback', [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
                return ['success' => false, 'message' => 'Processing error'];
            }
        }

        // ----------------------------
        // ✅ RENT FLOW (rent-)
        // ----------------------------
        if (str_starts_with($merchantOrderId, 'rent-')) {
            $parts        = explode('-', $merchantOrderId);
            $rentRequestId = intval($parts[1] ?? 0);
            $userId        = intval($parts[2] ?? 0);

            \Log::info('Processing rent- flow', [
                'rent_request_id' => $rentRequestId,
                'user_id' => $userId,
                'merchant_order_id' => $merchantOrderId
            ]);

            $purchase = Purchase::where('rent_request_id', $rentRequestId)
                ->where('user_id', $userId)
                ->whereIn('status', ['pending', 'pending_payment'])
                ->latest()
                ->first();

            if (!$purchase) {
                $purchase = Purchase::where('transaction_ref', $merchantOrderId)
                    ->orWhere('transaction_ref', $merchantOrderId)
                    ->first();
            }

            if (!$purchase) {
                $rentRequest = RentRequest::find($rentRequestId);
                if (!$rentRequest) {
                    \Log::error('Rent request not found', ['rent_request_id' => $rentRequestId]);
                    return ['success' => false, 'message' => 'Rent request not found'];
                }
                $purchase = Purchase::create([
                    'user_id'        => $userId,
                    'property_id'    => $rentRequest->property_id,
                    'rent_request_id'=> $rentRequest->id,
                    'amount'         => $rentRequest->total_price ?? ($rentRequest->nights * $rentRequest->price_per_night),
                    'deposit_amount' => $rentRequest->price_per_night ?? 0,
                    'payment_type'   => 'rent',
                    'status'         => 'pending',
                    'payment_gateway'=> 'paymob',
                    'idempotency_key'=> uniqid('rent_'),
                    'transaction_ref'=> $merchantOrderId,
                    'metadata'       => ['flow' => 'rent', 'merchant_order_id' => $merchantOrderId],
                ]);
            }

            try {
                DB::transaction(function () use ($purchase, $obj, $amount) {
                    $walletToUse = $purchase->metadata['wallet_to_use'] ?? 0;
                    if ($walletToUse > 0) {
                        $wallet = Wallet::lockForUpdate()->firstOrCreate(['user_id' => $purchase->user_id], ['balance' => 0]);
                        $before = $wallet->balance;
                        if ($wallet->balance < $walletToUse) {
                            $walletToUse = min($wallet->balance, $walletToUse);
                        }
                        if ($walletToUse > 0) {
                            $wallet->decrement('balance', $walletToUse);
                            WalletTransaction::create([
                                'wallet_id'      => $wallet->id,
                                'amount'         => -$walletToUse,
                                'type'           => 'rent_partial',
                                'ref_id'         => $purchase->id,
                                'ref_type'       => 'purchase',
                                'description'    => 'Partial rent payment from wallet',
                                'balance_before' => $before,
                                'balance_after'  => $wallet->balance,
                            ]);
                        }
                    }

            $purchase->update([
    'status'         => 'successful',
    'transaction_ref'=> $obj['id'] ?? $purchase->transaction_ref,
    'metadata'       => array_merge($purchase->metadata ?? [], ['paymob_txn' => $obj]),
]);


                    $rentRequest   = RentRequest::lockForUpdate()->find($purchase->rent_request_id);
                    $rentAmount    = $purchase->amount - ($purchase->deposit_amount ?? 0);
                    $depositAmount = $purchase->deposit_amount ?? ($rentRequest->price_per_night ?? 0);
                    $totalAmount   = $purchase->amount;

                    $escrow = EscrowBalance::firstOrCreate(
                        ['rent_request_id' => $purchase->rent_request_id],
                        [
                            'user_id'       => $purchase->user_id,
                            'rent_amount'   => $rentAmount,
                            'deposit_amount'=> $depositAmount,
                            'total_amount'  => $totalAmount,
                            'status'        => 'locked',
                            'locked_at'     => now(),
                        ]
                    );

                    if ($rentRequest && $rentRequest->status !== 'paid') {
                        $rentRequest->update(['status' => 'paid']);
                    }

                    try {
                        Notification::send($rentRequest->property->owner, new \App\Notifications\RentPaidByRenter($rentRequest));
                        Notification::send($rentRequest->user, new \App\Notifications\RentPaymentSuccessful($rentRequest));
                    } catch (\Exception $e) {
                        Log::warning('Notifications failed on rent callback', ['error' => $e->getMessage()]);
                    }
                });

                \Log::info('Rent flow completed successfully', [
                    'purchase_id' => $purchase->id,
                    'rent_request_id' => $purchase->rent_request_id
                ]);

                return [
                    'success'         => true,
                    'message'         => 'Rent payment processed',
                    'rent_request_id' => $purchase->rent_request_id,
                    'purchase_id'     => $purchase->id,
                ];
            } catch (\Throwable $e) {
                Log::error('Error processing rent callback: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
                return ['success' => false, 'message' => 'Processing rent payment failed'];
            }
        }

        \Log::warning('Unknown merchant order ID pattern', ['merchant_order_id' => $merchantOrderId]);
        return ['success' => false, 'message' => 'Unknown flow'];
    }

    /**
     * Create payment key.
     * Accepts optional metadata['merchant_order_id'] to set merchant_order_id explicitly (useful for rent flow).
     */
    public function createPaymentKey(array $data): string
    {
        $authToken = $this->generateToken();

        // determine merchant order id:
        if (!empty($data['metadata']['merchant_order_id'])) {
            $merchantOrderId = $data['metadata']['merchant_order_id'];
        } else {
            // choose default by flow
            $flow = $data['metadata']['flow'] ?? 'wallet';
            switch ($flow) {
                case 'rent_payment':
                    // expects rent_request_id + user in metadata
                    $rentRequestId = $data['metadata']['rent_request_id'] ?? uniqid();
                    $userId = $data['user']->id ?? '0';
                    $merchantOrderId = "rent-{$rentRequestId}-{$userId}-" . uniqid();
                    break;
                case 'buy':
                case 'property_purchase':
                    $propId = $data['metadata']['property_id'] ?? uniqid();
                    $userId = $data['user']->id ?? '0';
                    $merchantOrderId = "buy-{$propId}-{$userId}-" . uniqid();
                    break;
                case 'wallet':
                default:
                    $userId = $data['user']->id ?? '0';
                    $merchantOrderId = "wallet-{$userId}-" . uniqid();
                    break;
            }
        }

        // 1) Create Order
        $orderPayload = [
            "auth_token"        => $authToken,
            "delivery_needed"   => "false",
            "amount_cents"      => $data['amount_cents'],
            "currency"          => $data['currency'],
            "merchant_order_id" => $merchantOrderId,
            "items"             => [],
            "notification_url" => env("PAYMOB_CALLBACK_URL"),

        ];

        $orderResponse = $this->buildRequest('POST', '/api/ecommerce/orders', $orderPayload);
        $orderData = $orderResponse->getData(true);

        Log::info('Paymob order response', $orderData);

        $orderId = $orderData['id'] ?? ($orderData['data']['id'] ?? null);
        if (!$orderId) {
            throw new \Exception("Failed to create Paymob order: " . json_encode($orderData));
        }

        // 2) Create Payment Key
        $paymentPayload = [
            "auth_token"          => $authToken,
            "amount_cents"        => $data['amount_cents'],
            "currency"            => $data['currency'],
            "order_id"            => $orderId,
            "integration_id"      => 5306955,
            "lock_order_when_paid"=> true,
            "billing_data"        => [
                "email"        => $data['user']->email ?? "no-reply@example.com",
                "first_name"   => $data['user']->first_name ?? ($data['user']->name ?? "Guest"),
                "last_name"    => $data['user']->last_name ?? "User",
                "phone_number" => $data['user']->phone ?? "01000000000",
                "apartment"    => "NA",
                "floor"        => "NA",
                "street"       => "NA",
                "building"     => "NA",
                "shipping_method" => "NA",
                "postal_code"  => "NA",
                "city"         => "NA",
                "country"      => "EG",
                "state"        => "NA",
            ],
            "idempotency_key" => $data['idempotency_key'] ?? null,
            "return_url" => env("PAYMOB_RETURN_URL"), 
            "callback" => env("PAYMOB_CALLBACK_URL"), 
        ];

        $paymentResponse = $this->buildRequest('POST', '/api/acceptance/payment_keys', $paymentPayload);
        $paymentData = $paymentResponse->getData(true);

        $paymentToken = $paymentData['token'] ?? ($paymentData['data']['token'] ?? null);

        if (!$paymentToken) {
            throw new \Exception("Failed to generate Paymob payment key: " . json_encode($paymentData));
        }

        // Return token so frontend creates iframe using PAYMOB_IFRAME_URL + ?payment_token=...
        return $paymentToken;
    }
}