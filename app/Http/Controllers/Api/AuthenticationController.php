<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Jobs\sendOTPJOB;
use App\Models\RefreshToken;
use App\Models\User;
use App\Services\PhoneVerificationService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class AuthenticationController extends Controller
{
    public function __construct(public PhoneVerificationService $phone_verification_service)
    {

    }
    // Register new user
    public function register(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'first_name'    => 'required|string|max:255',
            'last_name'     => 'required|string|max:255',
            'email'         => 'required|string|email|max:255|unique:users',
            'password'      => 'required|string|min:6|confirmed',
            'phone_number'  => 'required|string|max:20|unique:users',
            'role'          => 'sometimes|in:user,owner,agent,admin',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $emailOtp = rand(100000, 999999);
        $emailOtpExpiresAt = now()->addMinutes(10);

        $user = User::create([
            'first_name'    => $request->first_name,
            'last_name'     => $request->last_name,
            'email'         => $request->email,
            'password'      => bcrypt($request->password),
            'phone_number'  => $request->phone_number,
            'role'          => $request->role ?? 'user', // Default is user
            'status'        => 'pending', // Default is pending
            'email_otp' => $emailOtp,
            'email_otp_expires_at' => $emailOtpExpiresAt,
        ]);
        sendOTPJOB::dispatch($user);

        return response()->json(
            [
                'message' => 'User registered successfully',
                 'user' => new UserResource($user)
            ], 201);
    }
     public function verifyEmailOtp(Request $request)
        {
            $validator = Validator::make($request->all(), [
                'user_id' => 'required|integer|exists:users,id',
                'otp'     => 'required|string|min:6|max:6',
            ]);

            if ($validator->fails()) {
                return response()->json(['errors' => $validator->errors()], 422);
            }

            $user = User::find($request->user_id);

            // to sure the user's email is not already verified
            if ($user->email_verified_at) {
                return response()->json(['message' => 'Email is already verified.'], 400);
            }

            // Check 1: Code Match & Check 2: Expiration
            if ($user->email_otp !== $request->otp || now()->isAfter($user->email_otp_expires_at)) {
                return response()->json(['message' => 'Invalid or expired verification code.'], 422);
            }

            // Verification Email successful
            $user->update([
                'email_verified_at' => now(),
                'email_otp' => null,
                'email_otp_expires_at' => null,
            ]);

            return response()->json(['message' => 'Email verified successfully']);
        }
        public function resendEmailOtp(Request $request)
        {
            $validator = Validator::make($request->all(), [
                'user_id' => 'required|integer|exists:users,id',
            ]);

            if ($validator->fails()) {
                return response()->json(['errors' => $validator->errors()], 422);
            }

            $user = User::find($request->user_id);

            if ($user->email_verified_at) {
                return response()->json(['message' => 'Email is already verified.'], 400);
            }

            $emailOtp = rand(100000, 999999);
            $emailOtpExpiresAt = now()->addMinutes(10);

            $user->update([
                'email_otp' => $emailOtp,
                'email_otp_expires_at' => $emailOtpExpiresAt,
            ]);

            sendOTPJOB::dispatch($user);

            return response()->json(['message' => 'A new verification code has been sent to your email.']);
        }
    public function sendPhoneOtp(Request $request)
        {

            $validator = Validator::make($request->all(), [
                'user_id' => 'required|integer|exists:users,id',
            ]);

            if ($validator->fails()) {
                return response()->json(['errors' => $validator->errors()], 422);
            }

            $user = User::find($request->user_id);

            if ($user->phone_verified_at) {
                return response()->json(['message' => 'Phone number is already verified.'], 400);
            }


            $otp = rand(100000, 999999);
            $expires_at = now()->addMinutes(10);


            $user->update([
                'whatsapp_otp' => $otp,
                'whatsapp_otp_expires_at' => $expires_at,
            ]);

               try {
                    $response = $this->phone_verification_service->sendOtpMessage($user->phone_number, $otp);


                    if ($response->failed()) {

                        Log::error('WhatsApp service failed to send OTP.', [
                            'user_id' => $user->id,
                            'status' => $response->status(),
                            'response_body' => $response->body(),
                        ]);

                        $user->update([
                            'whatsapp_otp' => null,
                            'whatsapp_otp_expires_at' => null,
                        ]);

                        return response()->json(['message' => 'We were unable to send a verification code to your phone. Please check the number and try again.'], 502); // 502 Bad Gateway is appropriate here
                    }


                } catch (\Throwable $e) {
                    $user->update([
                        'whatsapp_otp' => null,
                        'whatsapp_otp_expires_at' => null,
                    ]);

                    Log::error('WhatsApp OTP sending service threw an exception: ' . $e->getMessage());
                    return response()->json(['message' => 'Failed to send verification code due to a server error. Please try again later.'], 500);
                }

                return response()->json(['message' => 'A verification code has been sent to your phone.']);
            }



    /**
     * Verify the phone number using the submitted OTP.
     */
    public function verifyPhoneOtp(Request $request)
    {
        // 1. Validate the incoming request.
        $validator = Validator::make($request->all(), [
            'user_id' => 'required|integer|exists:users,id',
            'otp'     => 'required|string|min:6|max:6',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $user = User::find($request->user_id);

        // Security check: Ensure the phone is not already verified.
        if ($user->phone_verified_at) {
            return response()->json(['message' => 'Phone number is already verified.'], 400);
        }

        // 2. Perform the security checks.
        // Check 1: Does the submitted OTP match the one in the database?
        // Check 2: Is the current time before the expiration time?
        if ($user->whatsapp_otp !== $request->otp || now()->isAfter($user->whatsapp_otp_expires_at)) {
            return response()->json(['message' => 'Invalid or expired verification code.'], 422);
        }

        // 3. If checks pass, update the user record.
        $user->update([
            'phone_verified_at' => now(), // Mark the phone as verified.
            'whatsapp_otp' => null,          // CRUCIAL: Nullify the OTP so it cannot be used again.
            'whatsapp_otp_expires_at' => null,
        ]);

        return response()->json(['message' => 'Phone number verified successfully.']);
    }


    // Login user and return JWT token
    public function login(Request $request)
    {
        $credentials = $request->only('email', 'password');

        if (!$accessToken = auth('api')->attempt($credentials)) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $user = auth('api')->user();
        $refreshToken = Str::random(60);
        // Generate a new refresh token

        // Store the refresh token in the refresh_tokens table
        $this->storeRefreshToken($user, $refreshToken);

        // i will send access token in json and refresh in cookie
        return $this->respondWithToken($accessToken)
            ->withCookie($this->getRefreshTokenCookie($refreshToken));
    }

    // Get user profile
    public function profile()
    {
        return response()->json(auth('api')->user());
    }

    // Logout user (invalidate token)
    public function logout(Request $request)
    {
        $refreshToken = $request->cookie('refresh_token');

        if ($refreshToken) {
            // Remove the refresh token from the database
            RefreshToken::where('token', hash('sha256', $refreshToken))->delete();
        }

        auth('api')->logout();

        return response()->json(['message' => 'Successfully logged out'])
            ->withCookie(Cookie::forget('refresh_token'));
    }


    // Refresh JWT token
    public function refresh(Request $request)
    {
        $refreshToken = $request->cookie('refresh_token');

        if (!$refreshToken) {
            return response()->json(['error' => 'Refresh token not provided'], 401);
        }

        // Find the hashed token in the database
        $hashedToken = hash('sha256', $refreshToken);
        $tokenRecord = RefreshToken::where('token', $hashedToken)
            ->where('expires_at', '>', now())
            ->first();

        if (!$tokenRecord) {
            return response()->json(['error' => 'Invalid or expired refresh token'], 401);
        }

        $user = $tokenRecord->user;

        // Generate new tokens
        $accessToken = auth('api')->login($user);
        $newRefreshToken = Str::random(60);

        // Update the refresh token in the database (replace the old one)
        $tokenRecord->delete(); // Remove the old refresh token
        $this->storeRefreshToken($user, $newRefreshToken);

        return $this->respondWithToken($accessToken, new UserResource($user))
            ->withCookie($this->getRefreshTokenCookie($newRefreshToken));
    }

    // Return token response structure
    protected function respondWithToken($token, $userResource = null)
    {
        $response = [
            'access_token' => $token,
            'token_type'   => 'bearer',
            'expires_in'   => auth('api')->factory()->getTTL() * 60,
        ];

        // If a user resource is provided, merge it into the response.
        if ($userResource) {
            // 'user' => $userResource will add the user object under the 'user' key.
            $response['user'] = $userResource;
        }

        return response()->json($response);
    }
     protected function storeRefreshToken($user, $token)
    {
        $expiresAt = Carbon::now()->addMinutes(config('jwt.refresh_ttl', 20160));

        RefreshToken::create([
            'user_id' => $user->id,
            'token' => hash('sha256', $token),
            'expires_at' => $expiresAt,
        ]);
    }
    protected function getRefreshTokenCookie($token)
    {
    $isProduction = app()->environment('production');

    return cookie(
            'refresh_token',
            $token,
            config('jwt.refresh_ttl', 20160), // 2 weeks
            '/', // path
            null, // domain (adjust if needed)
            true, // secure (HTTPS only)
            true, // httpOnly
            false,
            'None' // sameSite
        );
    }


}
