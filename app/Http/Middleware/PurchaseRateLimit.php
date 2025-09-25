<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Support\Facades\Cache;

class PurchaseRateLimit
{
    public function handle($request, Closure $next)
    {
        $userId = $request->user()?->id ?? $request->ip();
        $key = "purchase_attempts:{$userId}";

        $attempts = Cache::get($key, 0);

        if ($attempts >= 2000) {
            return response()->json([
                'success' => false,
                'message' => 'You have reached the maximum of 4 purchase attempts per hour.'
            ], 429);
        }

        // increase attempt count, expire after 1 hour
        Cache::put($key, $attempts + 1, now()->addHour());

        return $next($request);
    }
}