<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PaymobCallbackController extends Controller
{
    public function handle(Request $request)
    {
        $data = $request->all();

        // HMAC validation completely disabled
        Log::info('HMAC validation disabled in PaymobCallbackController');
        
        Log::info('Paymob callback received', $data);

        // Here, call your existing logic to update payment status
        // Example: PaymentService::processCallback($data);

        return response()->json(['success' => true]);
    }
}