<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware: EnsureSuperAdmin
 *
 * Hanya mengizinkan pengguna dengan role 'superadmin' mengakses route yang dilindungi.
 * Harus digunakan SETELAH middleware auth:sanctum.
 *
 * Alias: 'superadmin'
 */
class EnsureSuperAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        if (!$request->user() || $request->user()->role !== 'superadmin') {
            return response()->json([
                'message'    => 'Akses ditolak. Endpoint ini hanya untuk Superadmin.',
                'error_code' => 'SUPERADMIN_ONLY',
            ], 403);
        }

        return $next($request);
    }
}
