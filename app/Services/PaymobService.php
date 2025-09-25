<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Exception;

class PaymobService
{
    private $baseUrl;
    private $apiKey;
    private $authToken;

    public function __construct()
    {
        $this->baseUrl = config('services.paymob.base_url', 'https://accept.paymob.com');
        $this->apiKey = config('services.paymob.api_key');
        
        if (!$this->apiKey) {
            throw new Exception('Paymob API key not configured');
        }
    }

    /**
     * Get authentication token from Paymob
     */
    private function authenticate()
    {
        if ($this->authToken) {
            return $this->authToken;
        }

        try {
            $response = Http::post("{$this->baseUrl}/api/auth/tokens", [
                'api_key' => $this->apiKey
            ]);

            if ($response->successful()) {
                $data = $response->json();
                $this->authToken = $data['token'];
                return $this->authToken;
            }

            throw new Exception('Paymob authentication failed: ' . $response->body());
        } catch (Exception $e) {
            Log::error('Paymob authentication error', ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * Create an order in Paymob
     */
    private function createOrder($amount, $metadata = [])
    {
        $token = $this->authenticate();

        $orderData = [
            'auth_token' => $token,
            'delivery_needed' => false,
            'amount_cents' => $amount * 100, // Convert to cents
            'currency' => 'EGP',
            'items' => [
                [
                    'name' => $metadata['description'] ?? 'Property Purchase',
                    'amount_cents' => $amount * 100,
                    'description' => $metadata['description'] ?? 'Property Purchase',
                    'quantity' => 1
                ]
            ]
        ];

        try {
            $response = Http::post("{$this->baseUrl}/api/ecommerce/orders", $orderData);

            if ($response->successful()) {
                return $response->json();
            }

            throw new Exception('Failed to create Paymob order: ' . $response->body());
        } catch (Exception $e) {
            Log::error('Paymob order creation error', ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * Get payment key for the transaction
     */
    private function getPaymentKey($orderId, $amount, $metadata = [])
    {
        $token = $this->authenticate();
        
        // You'll need to configure your integration ID in config
        $integrationId = config('services.paymob.integration_id');
        
        if (!$integrationId) {
            throw new Exception('Paymob integration ID not configured');
        }

        $paymentKeyData = [
            'auth_token' => $token,
            'amount_cents' => $amount * 100,
            'expiration' => 3600, // 1 hour
            'order_id' => $orderId,
            'billing_data' => [
                'apartment' => 'NA',
                'email' => $metadata['user_email'] ?? 'customer@example.com',
                'floor' => 'NA',
                'first_name' => $metadata['user_name'] ?? 'Customer',
                'street' => 'NA',
                'building' => 'NA',
                'phone_number' => $metadata['user_phone'] ?? '+201000000000',
                'shipping_method' => 'NA',
                'postal_code' => 'NA',
                'city' => 'Cairo',
                'country' => 'EG',
                'last_name' => 'Customer',
                'state' => 'Cairo'
            ],
            'currency' => 'EGP',
            'integration_id' => $integrationId
        ];

        try {
            $response = Http::post("{$this->baseUrl}/api/acceptance/payment_keys", $paymentKeyData);

            if ($response->successful()) {
                $data = $response->json();
                return $data['token'];
            }

            throw new Exception('Failed to get payment key: ' . $response->body());
        } catch (Exception $e) {
            Log::error('Paymob payment key error', ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * Process payment with saved card token
     */
    public function chargeCard($cardToken, $amount, $metadata = [])
    {
        try {
            Log::info('Processing Paymob payment', [
                'amount' => $amount,
                'metadata' => $metadata
            ]);

            // Step 1: Create order
            $order = $this->createOrder($amount, $metadata);
            $orderId = $order['id'];

            // Step 2: Get payment key
            $paymentKey = $this->getPaymentKey($orderId, $amount, $metadata);

            // Step 3: Process payment with card token
            $paymentData = [
                'source' => [
                    'identifier' => $cardToken,
                    'subtype' => 'TOKEN'
                ],
                'payment_token' => $paymentKey
            ];

            $response = Http::post("{$this->baseUrl}/api/acceptance/payments/pay", $paymentData);

            if ($response->successful()) {
                $data = $response->json();
                
                // Check if payment was successful
                if ($data['success'] === true) {
                    Log::info('Paymob payment successful', [
                        'transaction_id' => $data['id'],
                        'order_id' => $orderId
                    ]);

                    return [
                        'success' => true,
                        'transaction_id' => $data['id'],
                        'order_id' => $orderId,
                        'reference' => $data['txn_response_code'] ?? null,
                        'message' => 'Payment processed successfully',
                        'gateway_response' => $data
                    ];
                } else {
                    Log::warning('Paymob payment failed', [
                        'response' => $data,
                        'order_id' => $orderId
                    ]);

                    return [
                        'success' => false,
                        'message' => $data['data']['message'] ?? 'Payment failed',
                        'error_code' => $data['txn_response_code'] ?? null
                    ];
                }
            }

            throw new Exception('Payment request failed: ' . $response->body());

        } catch (Exception $e) {
            Log::error('Paymob charge card error', [
                'error' => $e->getMessage(),
                'amount' => $amount,
                'metadata' => $metadata
            ]);

            return [
                'success' => false,
                'message' => 'Payment processing failed: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Verify payment status by transaction ID
     */
    public function verifyPayment($transactionId)
    {
        try {
            $token = $this->authenticate();
            
            $response = Http::get("{$this->baseUrl}/api/acceptance/transactions/{$transactionId}", [
                'auth_token' => $token
            ]);

            if ($response->successful()) {
                return $response->json();
            }

            throw new Exception('Failed to verify payment: ' . $response->body());
        } catch (Exception $e) {
            Log::error('Paymob payment verification error', [
                'transaction_id' => $transactionId,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Create a refund
     */
    public function refundPayment($transactionId, $amount)
    {
        try {
            $token = $this->authenticate();
            
            $refundData = [
                'auth_token' => $token,
                'transaction_id' => $transactionId,
                'amount_cents' => $amount * 100
            ];

            $response = Http::post("{$this->baseUrl}/api/acceptance/void_refund/refund", $refundData);

            if ($response->successful()) {
                return $response->json();
            }

            throw new Exception('Failed to process refund: ' . $response->body());
        } catch (Exception $e) {
            Log::error('Paymob refund error', [
                'transaction_id' => $transactionId,
                'amount' => $amount,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }
}