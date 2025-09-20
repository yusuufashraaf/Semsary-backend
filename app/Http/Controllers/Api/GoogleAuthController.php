<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\RefreshToken;
use Illuminate\Http\Request; // <-- Often needed, good to have
use Illuminate\Support\Facades\Log;
use Laravel\Socialite\Facades\Socialite;
use Illuminate\Support\Str;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cookie;

class GoogleAuthController extends Controller
{
    /**
     * This is the function that was missing.
     * It redirects the user to Google's authentication page.
     */
    public function redirectToGoogle()
    {
        Log::info('--- Redirecting to Google for authentication ---');
        // The stateless() call is correct for an API-only backend.
        return Socialite::driver('google')->stateless()->redirect();
    }

    /**
     * This is the function that handles the user after they return from Google.
     */
       public function handleGoogleCallback()
    {
        $frontendUrl = env('FRONTEND_URL', 'http://localhost:5173');

        try {
            $googleUser = Socialite::driver('google')->stateless()->user();

            // Find or create the user
            $user = User::updateOrCreate(
                ['email' => $googleUser->email],
                [
                    'google_id' => $googleUser->id,
                    'first_name' => $googleUser->user['given_name'] ?? 'User',
                    'last_name' => $googleUser->user['family_name'] ?? '',
                    'email_verified_at' => now(),
                    'password' => bcrypt(Str::random(24)) // Only set if new user
                ]
            );

            // Generate tokens
            $accessToken = auth('api')->login($user);
            if (!$accessToken) {
                Log::error('Google Callback failed: auth()->login() failed for user ' . $user->id);
                return redirect($frontendUrl . '/login?error=auth_failed');
            }


            $refreshToken = Str::random(60);
            $this->storeRefreshToken($user, $refreshToken);

            // Option 1: Redirect with temporary token in URL
            // Frontend will exchange this for cookies via API call
            $tempToken = Str::random(32);
            cache()->put('oauth_temp_' . $tempToken, [
                'user_id' => $user->id,
                'access_token' => $accessToken,
                'refresh_token' => $refreshToken
            ], now()->addMinutes(1)); // Expires in 1 minute

            return redirect($frontendUrl . '/auth/callback?token=' . $tempToken);

        } catch (\Exception $e) {
            Log::error('Google Auth Failed Exception: ' . $e->getMessage(), [
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);
            return redirect($frontendUrl . '/login?error=google_auth_failed');
        }
    }

    /**
     * Exchange temporary token for actual auth tokens
     * This endpoint is called by the frontend after receiving the redirect
     */
    public function exchangeToken(Request $request)
    {
        $request->validate([
            'token' => 'required|string'
        ]);

        $tempToken = $request->token;
        $tokenData = cache()->pull('oauth_temp_' . $tempToken);

        if (!$tokenData) {
            return response()->json([
                'error' => 'Invalid or expired token'
            ], 400);
        }

        // Create response with tokens in JSON
        $response = response()->json([
            'user' => User::find($tokenData['user_id']),
            'access_token' => $tokenData['access_token'],
            'token_type' => 'bearer',
            'expires_in' => config('jwt.ttl') * 60
        ]);

        // Also set cookies for subsequent requests
        $accessTokenCookie = $this->getAccessTokenCookie($tokenData['access_token']);
        $refreshTokenCookie = $this->getRefreshTokenCookie($tokenData['refresh_token']);

        return $response
            ->withCookie($accessTokenCookie)
            ->withCookie($refreshTokenCookie);
    }

    // --- Helper Methods ---

    protected function getAccessTokenCookie($token)
    {
        return cookie('access_token', $token, config('jwt.ttl', 60), '/', env('COOKIE_DOMAIN', null), env('COOKIE_SECURE', false), false, false, 'Lax');
    }

    protected function getRefreshTokenCookie($token)
    {
        return cookie('refresh_token', $token, config('jwt.refresh_ttl', 20160), '/', env('COOKIE_DOMAIN', null), env('COOKIE_SECURE', false), true, false, 'Lax');
    }

    protected function storeRefreshToken($user, $token)
    {
        RefreshToken::where('user_id', $user->id)->delete();
        RefreshToken::create([
            'user_id' => $user->id,
            'token' => hash('sha256', $token),
            'expires_at' => now()->addMinutes(config('jwt.refresh_ttl', 20160)),
        ]);
    }
}
