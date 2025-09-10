<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\RefreshToken;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class AuthenticationController extends Controller
{
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

        $user = User::create([
            'first_name'    => $request->first_name,
            'last_name'     => $request->last_name,
            'email'         => $request->email,
            'password'      => bcrypt($request->password),
            'phone_number'  => $request->phone_number,
            'role'          => $request->role ?? 'user', // Default is user
            'status'        => 'pending', // Default is pending
        ]);

        return response()->json(['message' => 'User registered successfully', 'user' => $user], 201);
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

        return $this->respondWithToken($accessToken)
            ->withCookie($this->getRefreshTokenCookie($newRefreshToken));
    }

    // Return token response structure
    protected function respondWithToken($token)
    {
        return response()->json([
            'access_token' => $token,
            'token_type'   => 'bearer',
            'expires_in'   => auth('api')->factory()->getTTL() * 60,
        ]);
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
        return cookie(
            'refresh_token',
            $token,
            config('jwt.refresh_ttl', 20160), // 2 weeks in minutes (default)
            null,
            null,
            true,  // Secure: only over HTTPS
            true   // HttpOnly: not accessible via JS
        );
    }

}
