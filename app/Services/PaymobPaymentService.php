<?php

namespace App\Services;

use App\Interfaces\PaymentGatewayInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class PaymobPaymentService extends BasePaymentService implements PaymentGatewayInterface
{
    /**
     * Create a new class instance.
     */
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

        $this->integrations_id = [4865052, 4864845];
    }

//first generate token to access api
    protected function generateToken()
    {
    $response = $this->buildRequest('POST', '/api/auth/tokens', [
        'api_key' => $this->api_key
    ]);

    // Convert response to array
    $body = $response->getData(true);

    if (isset($body['token'])) {
        return $body['token'];
    }

    // Debug: store whole response if unexpected
    \Illuminate\Support\Facades\Log::error('Paymob auth failed', $body);

    throw new \Exception('Failed to generate Paymob token');
    }

    public function sendPayment(Request $request):array
    {
        $this->header['Authorization'] = 'Bearer ' . $this->generateToken();
        //validate data before sending it
        $data = $request->all();
        $data['api_source'] = "INVOICE";
        $data['integrations'] = $this->integrations_id;

        $response = $this->buildRequest('POST', '/api/ecommerce/orders', $data);
        //handel payment response data and return it
        if ($response->getData(true)['success']) {


            return ['success' => true, 'url' => $response->getData(true)['data']['url']];
        }

        return ['success' => false, 'url' => route('payment.failed')];
    }

  public function callBack(Request $request): array
{
    $response = $request->all();

    // Store raw payload for debugging
    Storage::put('paymob_response.json', json_encode($response));

    // Step 1: verify hash if Paymob sends hmac
    if (isset($response['hmac'])) {
        $calculatedHmac = $this->calculateHmac($response); // implement below
        if ($calculatedHmac !== $response['hmac']) {
            return [
                'success' => false,
                'message' => 'Invalid signature',
            ];
        }
    }

    // Step 2: check status directly from payload
    if (isset($response['success']) && $response['success'] === 'true') {
        return [
            'success' => true,
            'transaction_id' => $response['id'] ?? null,
            'amount' => isset($response['amount_cents']) ? $response['amount_cents'] / 100 : null,
            'currency' => $response['currency'] ?? null,
            'message' => 'Payment successful',
        ];
    }

    // Step 3: optionally verify by querying Paymob API
    if (isset($response['id'])) {
        $this->header['Authorization'] = 'Bearer ' . $this->generateToken();
        $verifyResponse = $this->buildRequest('GET', '/api/ecommerce/orders/' . $response['id'], []);
        $verifyData = $verifyResponse->getData(true);

        if (isset($verifyData['success']) && $verifyData['success'] == true) {
            return [
                'success' => true,
                'transaction_id' => $verifyData['data']['id'] ?? null,
                'amount' => $verifyData['data']['amount_cents'] / 100,
                'currency' => $verifyData['data']['currency'],
                'message' => 'Payment verified successfully',
            ];
        }
    }

    return [
        'success' => false,
        'message' => 'Payment failed or could not be verified',
    ];
}

/**
 * Calculate HMAC for Paymob callback validation
 */
protected function calculateHmac(array $payload): string
{
    $secret = env('PAYMOB_HMAC_SECRET'); // get from Paymob dashboard
    $keys = [
        'amount_cents', 'created_at', 'currency', 'error_occured',
        'has_parent_transaction', 'id', 'integration_id', 'is_3d_secure',
        'is_auth', 'is_capture', 'is_refunded', 'is_standalone_payment',
        'is_voided', 'order', 'owner', 'pending', 'source_data_pan',
        'source_data_sub_type', 'source_data_type', 'success'
    ];

    $concatenated = '';
    foreach ($keys as $key) {
        if (isset($payload[$key])) {
            $concatenated .= $payload[$key];
        }
    }

    return hash_hmac('sha512', $concatenated, $secret);
}


}
