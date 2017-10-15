<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpFoundation\Response;

/**
 * SECURITY OPTIMIZATION: Custom Rate Limiting
 * 
 * Protects API from:
 * - Brute force attacks
 * - DDoS attacks
 * - API abuse
 * 
 * Different limits for different endpoints:
 * - Login: 5 attempts per minute
 * - Location updates: 60 per minute
 * - General API: 120 per minute
 */
class CustomRateLimiter
{
    public function handle(Request $request, Closure $next, string $limit = 'api'): Response
    {
        $key = $this->resolveRequestSignature($request, $limit);
        
        $maxAttempts = $this->getMaxAttempts($limit);
        $decayMinutes = $this->getDecayMinutes($limit);

        if (RateLimiter::tooManyAttempts($key, $maxAttempts)) {
            $seconds = RateLimiter::availableIn($key);
            
            return response()->json([
                'error' => 'TOO_MANY_REQUESTS',
                'message' => 'Terlalu banyak permintaan. Silakan coba lagi dalam ' . $seconds . ' detik.',
                'retry_after' => $seconds
            ], 429);
        }

        RateLimiter::hit($key, $decayMinutes * 60);

        $response = $next($request);

        return $this->addRateLimitHeaders($response, $key, $maxAttempts);
    }

    protected function resolveRequestSignature(Request $request, string $limit): string
    {
        $user = $request->user();
        
        if ($user) {
            return "rate_limit:{$limit}:user:{$user->id}";
        }

        return "rate_limit:{$limit}:ip:" . $request->ip();
    }

    protected function getMaxAttempts(string $limit): int
    {
        return match($limit) {
            'login' => 5,           // 5 login attempts per minute
            'location' => 60,       // 60 location updates per minute
            'attendance' => 10,     // 10 attendance actions per minute
            'api' => 120,           // 120 general API calls per minute
            default => 60,
        };
    }

    protected function getDecayMinutes(string $limit): int
    {
        return match($limit) {
            'login' => 1,           // Reset after 1 minute
            'location' => 1,        // Reset after 1 minute
            'attendance' => 1,      // Reset after 1 minute
            'api' => 1,             // Reset after 1 minute
            default => 1,
        };
    }

    protected function addRateLimitHeaders(Response $response, string $key, int $maxAttempts): Response
    {
        $remaining = RateLimiter::remaining($key, $maxAttempts);
        $retryAfter = RateLimiter::availableIn($key);

        $response->headers->add([
            'X-RateLimit-Limit' => $maxAttempts,
            'X-RateLimit-Remaining' => max(0, $remaining),
        ]);

        if ($remaining === 0) {
            $response->headers->add([
                'X-RateLimit-Reset' => now()->addSeconds($retryAfter)->timestamp,
                'Retry-After' => $retryAfter,
            ]);
        }

        return $response;
    }
}
