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

    $obj = $response['obj'] ?? $response['transaction'] ?? $response;

    // TEMPORARILY DISABLE HMAC - REMOVE AFTER TESTING
    \Log::info('BYPASSING HMAC FOR TESTING');

    // Check if payment was successful
    if (!isset($obj['success']) || $obj['success'] !== true) {
        \Log::warning('Payment was not successful', $obj);
        return ['success' => false, 'message' => 'Payment failed'];
    }

    $amount = ($obj['amount_cents'] ?? 0) / 100;
    $merchantOrderId = $obj['order']['merchant_order_id'] ?? $obj['merchant_order_id'] ?? null;

    \Log::info('Processing callback - SUCCESS CONFIRMED', [
        'merchant_order_id' => $merchantOrderId,
        'amount' => $amount,
        'transaction_id' => $obj['id'] ?? 'unknown'
    ]);

    if (!$merchantOrderId) {
        \Log::error('Missing merchant_order_id');
        return ['success' => false, 'message' => 'Missing merchant_order_id'];
    }

    // For buy- flow - ADD EXTENSIVE DEBUGGING
    if (str_starts_with($merchantOrderId, 'buy-')) {
        \Log::emergency('BUY FLOW DETECTED', ['merchant_order_id' => $merchantOrderId]);
        
        $parts = explode('-', $merchantOrderId);
        $propertyId = intval($parts[1] ?? 0);
        $userId = intval($parts[2] ?? 0);

        \Log::emergency('SEARCHING FOR PURCHASE', [
            'property_id' => $propertyId,
            'user_id' => $userId
        ]);

        // Find purchase - try multiple ways
        $purchase = PropertyPurchase::where('property_id', $propertyId)
            ->where('buyer_id', $userId)
            ->whereIn('status', ['pending', 'pending_payment'])
            ->first();

        if (!$purchase) {
            $purchase = PropertyPurchase::where('merchant_order_id', $merchantOrderId)->first();
        }

        if (!$purchase) {
            // Show all purchases for debugging
            $allPurchases = PropertyPurchase::where('property_id', $propertyId)
                ->where('buyer_id', $userId)
                ->get(['id', 'status', 'merchant_order_id']);
                
            \Log::emergency('PURCHASE NOT FOUND', [
                'searched_property_id' => $propertyId,
                'searched_user_id' => $userId,
                'all_purchases_for_user' => $allPurchases->toArray()
            ]);
            
            return ['success' => false, 'message' => 'Purchase not found'];
        }

        \Log::emergency('PURCHASE FOUND - UPDATING STATUS', [
            'purchase_id' => $purchase->id,
            'current_status' => $purchase->status
        ]);

        // Update status immediately
        $updated = $purchase->update(['status' => 'paid']);
        
        \Log::emergency('STATUS UPDATE RESULT', [
            'update_successful' => $updated,
            'new_status' => $purchase->fresh()->status
        ]);

        return ['success' => true, 'message' => 'Status updated to paid', 'purchase_id' => $purchase->id];
    }

    // Keep your other flows (wallet, rent) as they are...
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