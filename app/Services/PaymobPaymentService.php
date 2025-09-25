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

    $obj = $response['obj'] ?? $response['transaction'] ?? $response;

    $hmacSecret = config('services.paymob.hmac'); 
$hmac= $response['hmac'] ?? null;

if (!$hmac) {
    return ['success' => false, 'message' => 'Missing HMAC'];
}

$concatenatedString =
    ($obj['amount_cents'] ?? '') .
    ($obj['created_at'] ?? '') .
    ($obj['currency'] ?? '') .
    ($obj['error_occured'] ?? '') .
    ($obj['has_parent_transaction'] ?? '') .
    ($obj['id'] ?? '') .
    ($obj['integration_id'] ?? '') .
    ($obj['is_3d_secure'] ?? '') .
    ($obj['is_auth'] ?? '') .
    ($obj['is_capture'] ?? '') .
    ($obj['is_refunded'] ?? '') .
    ($obj['is_standalone_payment'] ?? '') .
    ($obj['is_voided'] ?? '') .
    ($obj['order']['id'] ?? '') .
    ($obj['owner'] ?? '') .
    ($obj['pending'] ?? '') .
    ($obj['source_data']['pan'] ?? '') .
    ($obj['source_data']['sub_type'] ?? '') .
    ($obj['source_data']['type'] ?? '') .
    ($obj['success'] ?? '');

$calculatedHmac = hash_hmac('sha512', $concatenatedString, $hmacSecret);

if ($calculatedHmac !== $hmac) {
    \Log::warning('Paymob callback: HMAC mismatch', [
        'expected' => $calculatedHmac,
        'provided' => $hmac,
    ]);
    return ['success' => false, 'message' => 'Invalid HMAC'];
}


    $amount          = ($obj['amount_cents'] ?? 0) / 100;
    $merchantOrderId = $obj['order']['merchant_order_id'] ?? $obj['merchant_order_id'] ?? null;

    if (!$merchantOrderId) {
        return ['success' => false, 'message' => 'Missing merchant_order_id'];
    }

    // ----------------------------
    // ✅ WALLET TOPUP
    // ----------------------------
    if (str_starts_with($merchantOrderId, 'wallet-')) {
        $parts = explode('-', $merchantOrderId);
        $userId = intval($parts[1] ?? 0);
        $user   = User::find($userId);
        if (!$user) {
            return ['success' => false, 'message' => 'User not found'];
        }

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

        return ['success' => true, 'message' => 'Wallet topped up successfully', 'amount' => $amount];
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

                $purchase = PropertyPurchase::where('property_id', $propertyId)
                    ->where('buyer_id', $userId)
                    ->whereIn('status', ['pending', 'pending_payment', 'pending'])
                    ->latest()
                    ->first();

                if (!$purchase) {
                    $purchase = PropertyPurchase::where('transaction_ref', $merchantOrderId)->first();
                }

                if (!$purchase) {
                    return ['success' => false, 'message' => 'Property purchase not found or already processed'];
                }

                DB::transaction(function () use ($purchase, $obj) {
                    $purchase->update([
                        'status'          => 'paid',
                        'transaction_ref' => $obj['id'] ?? $purchase->transaction_ref,
                        'metadata'        => array_merge($purchase->metadata ?? [], ['paymob_txn' => $obj]),
                    ]);

                    PropertyEscrow::create([
                        'property_purchase_id' => $purchase->id,
                        'property_id'          => $purchase->property_id,
                        'buyer_id'             => $purchase->buyer_id,
                        'seller_id'            => $purchase->seller_id,
                        'amount'               => $purchase->amount,
                        'status'               => 'locked',
                        'locked_at'            => now(),
                        'scheduled_release_at' => now()->addHours(24),
                    ]);

                    $purchase->property->update([
                        'property_state'   => 'Invalid',
                        'pending_buyer_id' => $purchase->buyer_id,
                    ]);

                    try {
                        \Notification::send($purchase->buyer, new \App\Notifications\PropertyPurchaseInitiated($purchase));
                        \Notification::send($purchase->seller, new \App\Notifications\PurchaseInitiatedSeller($purchase));
                    } catch (\Exception $e) {
                        \Log::warning('Notification failed on Paymob callback (buy)', ['error' => $e->getMessage()]);
                    }
                });

                return ['success' => true, 'message' => 'Property purchase confirmed', 'purchase_id' => $purchase->id];
            }

            if (str_starts_with($merchantOrderId, 'purchase-')) {
                $purchaseId = intval(str_replace('purchase-', '', $merchantOrderId));
                $purchase   = PropertyPurchase::with('property', 'buyer', 'seller')->find($purchaseId);
                if (!$purchase) {
                    return ['success' => false, 'message' => 'Purchase not found'];
                }

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
                            'cancellation_deadline' => now()->addHours(24),
                        ]);

                        $purchase->property->update([
                            'property_state'   => 'Invalid',
                            'pending_buyer_id' => $purchase->buyer_id,
                        ]);

                        PropertyEscrow::create([
                            'property_purchase_id' => $purchase->id,
                            'property_id'          => $purchase->property_id,
                            'buyer_id'             => $purchase->buyer_id,
                            'seller_id'            => $purchase->seller_id,
                            'amount'               => $purchase->amount,
                            'status'               => 'locked',
                            'locked_at'            => now(),
                            'scheduled_release_at' => now()->addHours(24),
                        ]);
                    }
                });

                return ['success' => true, 'message' => 'Property purchase processed (purchase-)', 'purchase_id' => $purchase->id];
            }
        } catch (\Throwable $e) {
            \Log::error('Error processing buy/purchase callback: ' . $e->getMessage());
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

        $purchase = Purchase::where('rent_request_id', $rentRequestId)
            ->where('user_id', $userId)
            ->whereIn('status', ['pending', 'pending_payment', 'pending'])
            ->latest()
            ->first();

        if (!$purchase) {
            $purchase = Purchase::where('transaction_id', $merchantOrderId)
                ->orWhere('transaction_ref', $merchantOrderId)
                ->first();
        }

        if (!$purchase) {
            $rentRequest = RentRequest::find($rentRequestId);
            if (!$rentRequest) {
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
                    'status'         => 'paid',
                    'transaction_id' => $obj['id'] ?? $purchase->transaction_id,
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
            "integration_id"      => $this->integrations_id[0],
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
            "return_url"      => env("PAYMOB_RETURN_URL"),
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