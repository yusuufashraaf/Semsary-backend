<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
class resetPassVerification extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function resetPassword(Request $request)
    {


    $validator = Validator::make($request->all(), [
        'email'    => 'required|email|exists:users,email',
        'token'    => 'required|string',
        'password' => 'required|string|min:6|confirmed',
    ]);

    if ($validator->fails()) {
        return response()->json(['errors' => $validator->errors()], 422);
    }

    $tokenRecord = DB::table('password_reset_tokens')
        ->where('email', $request->email)
        ->where('token', $request->token)
        ->first();


    $expirationTime = config('auth.passwords.users.expire', 60);
    if (!$tokenRecord || now()->subMinutes($expirationTime)->isAfter($tokenRecord->created_at)) {
        return response()->json(['message' => 'Invalid or expired password reset token.'], 422);
    }

    $user = User::where('email', $request->email)->first();

    $user->update([
        'password' => Hash::make($request->password)
    ]);
    //delete token
    DB::table('password_reset_tokens')->where('email', $request->email)->delete();


    return response()->json(['message' => 'Your password has been reset successfully.']);

    }
    public function verifyToken(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'token' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => 'Invalid data provided.'], 422);
        }

        $tokenRecord = DB::table('password_reset_tokens')
            ->where('email', $request->email)
            ->where('token', $request->token)
            ->first();

        $expirationTime = config('auth.passwords.users.expire', 60);

        if (!$tokenRecord || now()->subMinutes($expirationTime)->isAfter($tokenRecord->created_at)) {
            return response()->json(['message' => 'Invalid or expired password reset token.'], 422);
        }

        return response()->json(['message' => 'Token is valid.'], 200);
    }
}
