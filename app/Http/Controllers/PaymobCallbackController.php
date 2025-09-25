<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PaymobCallbackController extends Controller
{
    public function handle(Request $request)
    {
        $secret = config('services.paymob.hmac_secret');
        $data = $request->all();

        // Extract the HMAC sent by Paymob
        $paymobHmac = $request->header('X-HMAC-Signature') ?? $request->input('hmac') ?? '';

        // Compute local HMAC for verification
        $localHmac = hash_hmac('sha512', json_encode($data), $secret);

        if (!hash_equals($localHmac, $paymobHmac)) {
            Log::warning('Paymob HMAC verification failed', $data);
            return response()->json(['success' => false, 'message' => 'Invalid HMAC'], 403);
        }

        Log::info('Paymob callback verified', $data);

        // Here, call your existing logic to update payment status
        // Example: PaymentService::processCallback($data);

        return response()->json(['success' => true]);
    }
}