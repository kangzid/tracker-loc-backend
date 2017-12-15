<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckDocsAccess
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Pastikan user sudah login (oleh auth.basic) dan memiliki role superadmin
        if (!$request->user() || $request->user()->role !== 'superadmin') {
            abort(403, 'Akses Dokumentasi Hanya Untuk Superadmin!');
        }

        return $next($request);
    }
}
