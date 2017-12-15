<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureSecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // HSTS (aktifkan hanya jika domain punya HTTPS valid)
        $response->headers->set(
            'Strict-Transport-Security',
            'max-age=31536000; includeSubDomains'
        );

        // Content Security Policy (CSP) - versi aman + support cdnjs & unpkg (untuk Scramble Docs)
        $response->headers->set(
            'Content-Security-Policy',
            "default-src 'self'; " .
            "script-src 'self' 'unsafe-inline' 'unsafe-eval' https://unpkg.com; " .
            "style-src 'self' 'unsafe-inline' https://cdnjs.cloudflare.com https://unpkg.com; " .
            "img-src 'self' data: blob:; " .
            "font-src 'self' https://cdnjs.cloudflare.com data:; " .
            "connect-src 'self' https://unpkg.com;"
        );

        // Clickjacking Protection
        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');

        // Prevent MIME-Type sniffing
        $response->headers->set('X-Content-Type-Options', 'nosniff');

        // Secure referrer
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');

        // Disable sensitive browser features
        $response->headers->set('Permissions-Policy', 'geolocation=(), microphone=(), camera=()');

        // XSS protection (browser legacy)
        $response->headers->set('X-XSS-Protection', '1; mode=block');

        return $response;
    }
}
