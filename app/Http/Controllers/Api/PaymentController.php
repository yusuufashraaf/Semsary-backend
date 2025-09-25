<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\PaymobPaymentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use App\Models\User;
use App\Models\PropertyEscrow;
use App\Models\EscrowBalance;

class PaymentController extends Controller
{
    protected PaymobPaymentService $paymob;

    public function __construct(PaymobPaymentService $paymob)
    {
        $this->paymob = $paymob;
    }

public function handle(Request $request)
{
    \Log::info('Paymob callback received at handle method', $request->all());

    try {
        $result = $this->paymob->callBack($request);
        
        \Log::info('Paymob callback result', $result);

        if (!$result['success']) {
            \Log::error('Paymob callback failed', $result);
            return response()->json($result, 400);
        }

        return response()->json(['message' => 'Callback handled successfully', 'data' => $result]);
        
    } catch (\Exception $e) {
        \Log::error('Exception in Paymob callback handle', [
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ]);
        return response()->json(['error' => 'Callback processing failed'], 500);
    }
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

}