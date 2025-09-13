<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\sendResetPassJob;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class forgetPasswordController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function forgetPassword(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email|exists:users,email',
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => 'If a matching account was found, a password reset link has been sent.'], 200);
        }


        $token = Str::random(60);
          DB::table('password_reset_tokens')->updateOrInsert(
            ['email' => $request->email],
            [
                'token' => $token,
                'created_at' => now()
            ]
        );
        sendResetPassJob::dispatch($token, $request->email);

        return response()->json(['message' => 'If a matching account was found, a password reset link has been sent.'], 200);
    }
}
