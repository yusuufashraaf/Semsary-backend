<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\sendOTPJOB;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class ProfileController extends Controller
{
    public function changeEmail(Request $request)
    {

        $user = Auth::user();

        $request->validate([
            'email' => [
                'required',
                'string',
                'email',
                'max:255',
                // Ensure the new email is unique in the 'users' table,
                // but ignore the current user's own email.
                Rule::unique('users')->ignore($user->id),
            ],
            'current_password' => ['required', 'string'],
        ]);
        if (!Hash::check($request->current_password, $user->password)) {
            return response()->json([
                'message' => 'The provided password does not match our records.'
            ], 422);
        }

        // Update the user's email and mark it as unverified
        $user->email = $request->input('email');
        $user->email_verified_at = null; // CRITICAL: Mark email as unverified
        $user->save();

        sendOTPJOB::dispatch($user);

        return response()->json([
            'message' => 'Verification email sent. Please check your new email address to complete the change.'
        ], 200);
    }
    public function changePhoneNumber(Request $request)
    {

        $user = Auth::user();


        $request->validate([
            'phone_number' => [
                'required',
                'string',
                'regex:/^[\d\s\+\-\(\)]+$/',
                'min:10',
                Rule::unique('users')->ignore($user->id),
            ],
            'password' => [
                'required',
                'string',
            ],
        ]);

        if (!Hash::check($request->password, $user->password)) {
            return response()->json([
                'message' => 'The provided password does not match our records.'
            ], 422);
        }


        $user->phone_number = $request->input('phone_number');
        $user->phone_verified_at = null;
        $user->save();



        return response()->json([
            'message' => 'An new phone number has been set. Please use it to verify.'
        ], 200);
    }
     public function changePassword(Request $request): JsonResponse
    {
        $user = Auth::user();

        // Validate the request data
        $validated = $request->validate([
            'current_password' => ['required', 'string'],
            'new_password' => [
                'required',
                'string',
                'confirmed',
                Password::min(8)->mixedCase()->numbers()->symbols(),
            ],
        ]);


        if (!Hash::check($validated['current_password'], $user->password)) {
            return response()->json([
                'message' => 'The provided current password does not match our records.'
            ], 422);
        }

        $user->password = Hash::make($validated['new_password']);
        $user->save();


        return response()->json([
            'message' => 'Your password has been changed successfully.'
        ], 200);
    }
}
