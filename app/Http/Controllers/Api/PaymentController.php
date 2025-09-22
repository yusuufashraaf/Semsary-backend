<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Interfaces\PaymentGatewayInterface;
use Illuminate\Http\Request;

class PaymentController extends Controller
{
    protected PaymentGatewayInterface $paymentGateway;

    public function __construct(PaymentGatewayInterface $paymentGateway)
    {

        $this->paymentGateway = $paymentGateway;

    }


    public function paymentProcess(Request $request)
    {

        return $this->paymentGateway->sendPayment($request);


    }
    public function callBack(Request $request): \Illuminate\Http\RedirectResponse
    {
        $frontendUrl = env('FRONTEND_URL', 'http://localhost:5173');

        try {
            $payment = $this->paymentGateway->callBack($request);
            $payment = ['success' => true, 'transaction_id' => 'TX123'];
            // If your gateway returns success/failure data
            if ($payment && isset($payment['success']) && $payment['success'] === true) {
                // Store a short-lived cache entry (optional)
                $tempToken = \Illuminate\Support\Str::random(32);

                cache()->put('payment_temp_' . $tempToken, [
                    'payment' => $payment,
                ], now()->addMinutes(1)); // expires in 1 minute

                // Redirect frontend with token in query
                return redirect($frontendUrl . '/payment/callback?token=' . $tempToken);
            }

            // If failed, redirect with error message
            $reason = $payment['message'] ?? 'payment_failed';
            return redirect($frontendUrl . '/payment/callback?error=' . urlencode($reason));

        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Payment Callback failed: ' . $e->getMessage());
            return redirect($frontendUrl . '/payment/callback?error=server_error');
        }
    }
    public function exchangePaymentToken(Request $request)
    {
        $request->validate([
            'token' => 'required|string',
        ]);

        $tempToken = $request->token;
        $paymentData = cache()->pull('payment_temp_' . $tempToken);

        if (!$paymentData) {
            return response()->json([
                'success' => false,
                'error' => 'Invalid or expired token',
            ], 400);
        }

        return response()->json([
            'success' => true,
            'payment' => $paymentData['payment'] ?? null,
        ]);
    }





}
