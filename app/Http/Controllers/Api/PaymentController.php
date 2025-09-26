<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\PaymobPaymentService;
use App\Models\PropertyPurchase;
use App\Models\PropertyEscrow;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PaymentController extends Controller
{
    protected PaymobPaymentService $paymob;

    public function __construct(PaymobPaymentService $paymob)
    {
        $this->paymob = $paymob;
    }

    /**
     * Handle Paymob callback with HMAC validation and frontend redirects
     */
    public function handle(Request $request)
    {
        Log::info('Paymob callback received', [
            'method' => $request->method(),
            'query_params' => $request->query(),
            'all_data' => $request->all(),
            'raw_query' => $request->getQueryString()
        ]);

        try {
            // Validate HMAC first
            if (!$this->validateHmac($request)) {
                Log::error('HMAC validation failed', [
                    'received_hmac' => $request->query('hmac'),
                    'calculated_hmac' => $this->calculateHmac($request)
                ]);
                
                return redirect()->to(env('FRONTEND_URL', 'http://localhost:3000') . '/payment/failed?error=invalid_hmac');
            }

            // Extract payment data
            $merchantOrderId = $request->query('merchant_order_id');
            $success = $request->query('success') === 'true';
            $amount = $request->query('amount_cents');
            $transactionId = $request->query('id');
            $currency = $request->query('currency', 'EGP');
            
            Log::info('Processing Paymob callback', [
                'merchant_order_id' => $merchantOrderId,
                'success' => $success,
                'amount' => $amount,
                'transaction_id' => $transactionId
            ]);

            // Process the payment based on merchant_order_id
            if ($success) {
                $this->processSuccessfulPayment($merchantOrderId, $transactionId, $amount);
            } else {
                $this->processFailedPayment($merchantOrderId, $request->query('error_message', 'Payment failed'));
            }

            // Get frontend URL
            $frontendUrl = rtrim(env('FRONTEND_URL', 'http://localhost:3000'), '/');
            
            if ($success) {
                // Success - redirect to success page
                $successUrl = $frontendUrl . '/payment/success?' . http_build_query([
                    'order_id' => $merchantOrderId,
                    'transaction_id' => $transactionId,
                    'amount' => $amount / 100, // Convert cents to dollars
                    'currency' => $currency,
                    'status' => 'completed'
                ]);
                
                Log::info('Redirecting to success page', ['url' => $successUrl]);
                return redirect()->to($successUrl);
                
            } else {
                // Failed - redirect to failure page
                $failureUrl = $frontendUrl . '/payment/failed?' . http_build_query([
                    'order_id' => $merchantOrderId,
                    'error' => 'payment_failed',
                    'message' => $request->query('error_message', 'Payment processing failed')
                ]);
                
                Log::info('Redirecting to failure page', ['url' => $failureUrl]);
                return redirect()->to($failureUrl);
            }
            
        } catch (\Exception $e) {
            Log::error('Exception in Paymob callback', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'request_data' => $request->all()
            ]);
            
            // Redirect to error page
            $errorUrl = env('FRONTEND_URL', 'http://localhost:3000') . '/payment/error?' . http_build_query([
                'message' => 'An error occurred during payment processing'
            ]);
            
            return redirect()->to($errorUrl);
        }
    }

    /**
     * Process successful payment
     */
    private function processSuccessfulPayment($merchantOrderId, $transactionId, $amountCents)
    {
        if (!$merchantOrderId) {
            Log::warning('No merchant_order_id provided');
            return;
        }

        try {
            // Determine flow type from merchant_order_id
            if (str_starts_with($merchantOrderId, 'buy-')) {
                $this->processPropertyPurchase($merchantOrderId, $transactionId, $amountCents);
            } elseif (str_starts_with($merchantOrderId, 'wallet-')) {
                $this->processWalletTopup($merchantOrderId, $transactionId, $amountCents);
            } else {
                Log::warning('Unknown merchant_order_id format', ['merchant_order_id' => $merchantOrderId]);
            }
        } catch (\Exception $e) {
            Log::error('Error processing successful payment', [
                'merchant_order_id' => $merchantOrderId,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Process property purchase payment
     */
    private function processPropertyPurchase($merchantOrderId, $transactionId, $amountCents)
    {
        DB::transaction(function () use ($merchantOrderId, $transactionId, $amountCents) {
            $purchase = PropertyPurchase::where('merchant_order_id', $merchantOrderId)
                ->where('status', 'pending')
                ->lockForUpdate()
                ->first();

            if (!$purchase) {
                Log::warning('Property purchase not found or already processed', [
                    'merchant_order_id' => $merchantOrderId
                ]);
                return;
            }

            // Update purchase status
            $purchase->update([
                'status' => 'paid',
                'transaction_ref' => $transactionId,
            ]);

            // Get wallet balance to use (from metadata)
            $walletToUse = $purchase->metadata['wallet_to_use'] ?? 0;
            
            if ($walletToUse > 0) {
                // Deduct wallet balance
                $wallet = Wallet::lockForUpdate()->where('user_id', $purchase->buyer_id)->first();
                if ($wallet && $wallet->balance >= $walletToUse) {
                    $beforeBalance = $wallet->balance;
                    $wallet->decrement('balance', $walletToUse);

                    // Record wallet transaction
                    WalletTransaction::create([
                        'wallet_id' => $wallet->id,
                        'amount' => -$walletToUse,
                        'type' => 'property_purchase',
                        'ref_id' => $purchase->id,
                        'ref_type' => 'property_purchase',
                        'description' => 'Property purchase - wallet portion',
                        'balance_before' => $beforeBalance,
                        'balance_after' => $wallet->balance,
                    ]);
                }
            }

            // Create or update escrow
            $escrow = PropertyEscrow::firstOrCreate(
                ['property_purchase_id' => $purchase->id],
                [
                    'property_id' => $purchase->property_id,
                    'buyer_id' => $purchase->buyer_id,
                    'seller_id' => $purchase->seller_id,
                    'amount' => $purchase->amount,
                    'status' => 'locked',
                    'locked_at' => now(),
                    'scheduled_release_at' => now()->addDays(3),
                ]
            );

            // Update property status
            $purchase->property->update([
                'status' => 'sold',
                'property_state' => 'Sold',
                'pending_buyer_id' => null,
            ]);

            Log::info('Property purchase completed successfully', [
                'purchase_id' => $purchase->id,
                'transaction_ref' => $transactionId,
                'escrow_id' => $escrow->id
            ]);

            // Send notifications
            try {
                $buyerNotification = new \App\Notifications\PropertyPurchaseSuccessful($purchase);
                \Notification::send($purchase->buyer, $buyerNotification);

                $sellerNotification = new \App\Notifications\PropertyPurchaseSuccessful($purchase);
                \Notification::send($purchase->seller, $sellerNotification);
            } catch (\Exception $e) {
                Log::warning('Notification failed for property purchase', [
                    'error' => $e->getMessage()
                ]);
            }
        });
    }

    /**
     * Process wallet top-up
     */
    private function processWalletTopup($merchantOrderId, $transactionId, $amountCents)
    {
        // Extract user ID from merchant_order_id (format: wallet-{userId}-{uuid})
        $parts = explode('-', $merchantOrderId);
        if (count($parts) < 2) {
            Log::warning('Invalid wallet merchant_order_id format', ['merchant_order_id' => $merchantOrderId]);
            return;
        }

        $userId = $parts[1];
        $amount = $amountCents / 100; // Convert cents to dollars

        DB::transaction(function () use ($userId, $amount, $transactionId, $merchantOrderId) {
            $wallet = Wallet::lockForUpdate()->firstOrCreate(
                ['user_id' => $userId],
                ['balance' => 0]
            );

            $beforeBalance = $wallet->balance;
            $wallet->increment('balance', $amount);

            WalletTransaction::create([
                'wallet_id' => $wallet->id,
                'amount' => $amount,
                'type' => 'topup',
                'ref_id' => $transactionId,
                'ref_type' => 'paymob_topup',
                'description' => 'Wallet top-up via Paymob',
                'balance_before' => $beforeBalance,
                'balance_after' => $wallet->balance,
            ]);

            Log::info('Wallet top-up completed', [
                'user_id' => $userId,
                'amount' => $amount,
                'transaction_ref' => $transactionId,
                'new_balance' => $wallet->balance
            ]);
        });
    }

    /**
     * Process failed payment
     */
    private function processFailedPayment($merchantOrderId, $errorMessage)
    {
        if (!$merchantOrderId) return;

        try {
            if (str_starts_with($merchantOrderId, 'buy-')) {
                $purchase = PropertyPurchase::where('merchant_order_id', $merchantOrderId)
                    ->where('status', 'pending')
                    ->first();

                if ($purchase) {
                    $purchase->update([
                        'status' => 'failed',
                        'metadata' => array_merge($purchase->metadata ?? [], [
                            'error_message' => $errorMessage,
                            'failed_at' => now()->toISOString()
                        ])
                    ]);

                    // Reset property status
                    $purchase->property->update([
                        'status' => 'sale',
                        'property_state' => 'Valid',
                        'pending_buyer_id' => null,
                    ]);

                    Log::info('Property purchase marked as failed', [
                        'purchase_id' => $purchase->id,
                        'error' => $errorMessage
                    ]);
                }
            }
        } catch (\Exception $e) {
            Log::error('Error processing failed payment', [
                'merchant_order_id' => $merchantOrderId,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Validate HMAC signature
     */
    private function validateHmac(Request $request)
    {
        $receivedHmac = $request->query('hmac');
        
        if (!$receivedHmac) {
            Log::error('No HMAC provided in callback');
            return false;
        }
        
        $calculatedHmac = $this->calculateHmac($request);
        
        Log::info('HMAC validation', [
            'received' => $receivedHmac,
            'calculated' => $calculatedHmac,
            'match' => hash_equals($receivedHmac, $calculatedHmac)
        ]);
        
        return hash_equals($receivedHmac, $calculatedHmac);
    }

    /**
     * Calculate HMAC signature
     */
    private function calculateHmac(Request $request)
    {
        $hmacSecret = env('PAYMOB_HMAC_SECRET');
        
        if (!$hmacSecret) {
            Log::error('PAYMOB_HMAC_SECRET not configured');
            throw new \Exception('HMAC secret not configured');
        }
        
        // Get all query parameters except HMAC
        $params = $request->query();
        unset($params['hmac']);
        
        // Sort parameters alphabetically by key
        ksort($params);
        
        // Build query string
        $queryString = '';
        foreach ($params as $key => $value) {
            if ($queryString !== '') {
                $queryString .= '&';
            }
            $queryString .= $key . '=' . $value;
        }
        
        Log::info('HMAC calculation', [
            'query_string' => $queryString,
            'secret_length' => strlen($hmacSecret)
        ]);
        
        // Calculate HMAC-SHA512
        return hash_hmac('sha512', $queryString, $hmacSecret);
    }

    /**
     * Top up wallet (existing method)
     */
    public function topUpWallet(Request $request)
    {
        $request->validate([
            'amount' => 'required|numeric|min:10',
        ]);

        $user = $request->user();

        // Generate merchant_order_id for wallet top-up
        $merchantOrderId = 'wallet-' . $user->id . '-' . Str::uuid()->toString();

        $paymentKey = $this->paymob->createPaymentKey([
            'amount_cents'   => intval($request->amount * 100),
            'currency'       => 'EGP',
            'user'           => $user,
            'metadata'       => [
                'merchant_order_id' => $merchantOrderId,
                'flow' => 'wallet_topup',
                'user_id' => $user->id,
            ]
        ]);

        return response()->json([
            'success'     => true,
            'payment_key' => $paymentKey,
            'iframe_url'  => env('PAYMOB_IFRAME_URL') . '?payment_token=' . $paymentKey,
            'merchant_order_id' => $merchantOrderId,
        ]);
    }
}