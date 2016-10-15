<?php

namespace App\Http\Middleware;

use App\Models\Subscription;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware: CheckSubscription
 *
 * Digunakan pada endpoint yang memerlukan kuota (store employee / store vehicle).
 * Mengecek dua kondisi:
 *   1. Apakah akun Admin punya subscription yang aktif & belum expired.
 *   2. Apakah kuota (max_employees / max_vehicles) masih tersisa.
 *
 * Penggunaan di route:
 *   - Route::post('/employees', ...)->middleware('subscription.quota:employee');
 *   - Route::post('/vehicles', ...)->middleware('subscription.quota:vehicle');
 */
class CheckSubscription
{
    public function handle(Request $request, Closure $next, string $type = 'employee'): Response
    {
        $user = $request->user();

        // Hanya Admin yang punya subscription — karyawan dan superadmin dilewati
        if (!$user || !$user->isAdmin()) {
            return $next($request);
        }

        // Ambil subscription aktif milik admin ini
        $subscription = Subscription::where('user_id', $user->id)
            ->where('status', 'active')
            ->latest()
            ->first();

        // Jika tidak ada subscription sama sekali → tolak
        if (!$subscription) {
            return response()->json([
                'message'       => 'Akun belum memiliki paket berlangganan aktif. Silakan hubungi administrator.',
                'error_code'    => 'NO_SUBSCRIPTION',
            ], 403);
        }

        // Jika subscription sudah expired → tolak dan update status
        if ($subscription->expired_at->isPast()) {
            $subscription->update(['status' => 'expired']);
            return response()->json([
                'message'       => 'Masa berlangganan Anda telah habis. Silakan upgrade atau perpanjang paket.',
                'error_code'    => 'SUBSCRIPTION_EXPIRED',
                'expired_at'    => $subscription->expired_at->toDateTimeString(),
            ], 403);
        }

        // Cek kuota berdasarkan tipe yang diminta
        if ($type === 'employee') {
            // Tahap 1 (single-admin): hitung semua employee yang ada di sistem
            $currentCount = \App\Models\Employee::count();

            if ($currentCount >= $subscription->max_employees) {
                return response()->json([
                    'message'       => "Kuota karyawan Anda sudah penuh ({$subscription->max_employees} dari {$subscription->max_employees}). Upgrade paket untuk menambah lebih banyak.",
                    'error_code'    => 'EMPLOYEE_QUOTA_EXCEEDED',
                    'current'       => $currentCount,
                    'max'           => $subscription->max_employees,
                ], 403);
            }
        }

        if ($type === 'vehicle') {
            $currentCount = \App\Models\Vehicle::count();

            if ($currentCount >= $subscription->max_vehicles) {
                return response()->json([
                    'message'       => "Kuota kendaraan Anda sudah penuh ({$subscription->max_vehicles} dari {$subscription->max_vehicles}). Upgrade paket untuk menambah lebih banyak.",
                    'error_code'    => 'VEHICLE_QUOTA_EXCEEDED',
                    'current'       => $currentCount,
                    'max'           => $subscription->max_vehicles,
                ], 403);
            }
        }

        return $next($request);
    }
}
