<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;

class VerificationController extends Controller
{
//    public function verifyEmail(Request $request, $id, $hash)
// {
//     $user = User::findOrFail($id);

//     if (!hash_equals((string) $hash, sha1($user->getEmailForVerification()))) {
//         return response()->json(['error' => 'Invalid verification link'], 400);
//     }

//     if ($user->hasVerifiedEmail()) {
//         return response()->json(['message' => 'Email already verified']);
//     }

//     $user->markEmailAsVerified();

//     return response()->json(['message' => 'Email verified successfully']);
// }

// // Verify phone (you'll need to implement your phone verification logic)
// public function verifyPhone(Request $request)
// {
//     $validator = Validator::make($request->all(), [
//         'phone_number' => 'required|exists:users,phone_number',
//         'verification_code' => 'required',
//     ]);

//     if ($validator->fails()) {
//         return response()->json(['errors' => $validator->errors()], 422);
//     }

//     $user = User::where('phone_number', $request->phone_number)->first();

//     // Implement your verification code check logic here
//     // This is just a placeholder example
//     if ($request->verification_code === '123456') { // Replace with actual verification logic
//         $user->phone_verified_at = now();
//         $user->save();

//         return response()->json(['message' => 'Phone number verified successfully']);
//     }

//     return response()->json(['error' => 'Invalid verification code'], 400);
// }

// // Resend verification email
// public function resendVerificationEmail(Request $request)
// {
//     $user = $request->user();

//     if ($user->hasVerifiedEmail()) {
//         return response()->json(['message' => 'Email already verified']);
//     }

//     $user->sendEmailVerificationNotification();

//     return response()->json(['message' => 'Verification email sent']);
// }


// Route::get('/email/verify/{id}/{hash}', [AuthenticationController::class, 'verifyEmail'])
//     ->name('verification.verify');

// Route::post('/email/resend', [AuthenticationController::class, 'resendVerificationEmail'])
//     ->middleware('auth:api')
//     ->name('verification.resend');

// Route::post('/phone/verify', [AuthenticationController::class, 'verifyPhone']);
}
