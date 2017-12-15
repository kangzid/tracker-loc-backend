<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

/**
 * ProvisionController
 *
 * Endpoint publik untuk mendaftarkan client baru ke dalam sistem SaaS.
 * Client mengirim nama perusahaan + email + nomor HP.
 * Sistem otomatis buatkan akun Admin + subscription trial 30 hari.
 * Kredensial dikembalikan sekali dalam response (password plaintext, hanya muncul saat itu).
 */
class ProvisionController extends Controller
{
    /**
     * POST /api/provision
     * Public endpoint — tidak memerlukan token autentikasi.
     */
    public function provision(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'company_name'  => 'required|string|max:255',
            'email'         => 'required|email|unique:users,email',
            'contact_phone' => 'nullable|string|max:20',
        ], [
            'company_name.required'  => 'Nama perusahaan wajib diisi.',
            'email.required'         => 'Email wajib diisi.',
            'email.email'            => 'Format email tidak valid.',
            'email.unique'           => 'Email ini sudah terdaftar dalam sistem. Gunakan email lain atau hubungi kami.',
            'contact_phone.max'      => 'Nomor telepon maksimal 20 karakter.',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validasi gagal.',
                'errors'  => $validator->errors(),
            ], 422);
        }

        // Generate password acak yang kuat (12 karakter)
        $plainPassword = Str::password(12, letters: true, numbers: true, symbols: false, spaces: false);

        // Buat nama admin dari nama perusahaan
        $adminName = 'Admin ' . $request->company_name;

        try {
            // 1. Buat akun User dengan role=admin
            $user = new User([
                'name'      => $adminName,
                'email'     => $request->email,
                'password'  => Hash::make($plainPassword),
                'is_active' => true,
            ]);
            $user->role = 'admin';
            $user->save();

            // 2. Buat subscription trial 30 hari
            $trialPlan = \App\Models\Plan::where('slug', 'trial')->first();
            
            $subscription = Subscription::create([
                'user_id'       => $user->id,
                'plan_id'       => $trialPlan ? $trialPlan->id : null,
                'max_employees' => $trialPlan ? $trialPlan->max_employees : 1,
                'max_vehicles'  => $trialPlan ? $trialPlan->max_vehicles : 1,
                'ai_credits_limit' => $trialPlan ? $trialPlan->ai_credits : 20,
                'company_name'  => $request->company_name,
                'contact_phone' => $request->contact_phone,
                'started_at'    => now(),
                'expired_at'    => now()->addDays(30),
                'status'        => 'active',
            ]);

            return response()->json([
                'message' => 'Akun trial berhasil dibuat! Simpan kredensial berikut dengan aman. Password tidak akan ditampilkan kembali.',
                'data'    => [
                    'company_name'      => $request->company_name,
                    'admin_name'        => $adminName,
                    'email'             => $request->email,
                    'password'          => $plainPassword, // Plaintext, hanya muncul sekali
                    'plan'              => 'trial',
                    'max_employees'     => $subscription->max_employees,
                    'max_vehicles'      => $subscription->max_vehicles,
                    'trial_started_at'  => $subscription->started_at->toDateTimeString(),
                    'trial_expires_at'  => $subscription->expired_at->toDateTimeString(),
                    'days_remaining'    => 30,
                ],
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Gagal membuat akun. Silakan coba lagi.',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * GET /api/subscription/status
     * Endpoint untuk Admin melihat status dan detail subscription-nya sendiri.
     * Memerlukan token autentikasi.
     */
    public function status(Request $request)
    {
        $user = $request->user();

        if (!$user->isAdmin()) {
            return response()->json(['message' => 'Unauthorized. Hanya Admin yang dapat mengakses endpoint ini.'], 403);
        }

        $subscription = Subscription::with('planDetails')->where('user_id', $user->id)
            ->latest()
            ->first();

        if (!$subscription) {
            return response()->json([
                'message'           => 'Tidak ada subscription ditemukan untuk akun ini.',
                'has_subscription'  => false,
            ], 404);
        }

        // Auto-update status jika sudah expired
        if ($subscription->expired_at->isPast() && $subscription->status === 'active') {
            $subscription->update(['status' => 'expired']);
        }

        // Hitung usage saat ini (hanya milik admin ini - tenant isolation)
        $currentEmployees = \App\Models\Employee::where('admin_id', $user->id)->count();
        $currentVehicles  = \App\Models\Vehicle::where('admin_id', $user->id)->count();

        return response()->json([
            'has_subscription'  => true,
            'subscription'      => [
                'id'                => $subscription->id,
                'company_name'      => $subscription->company_name,
                'plan'              => $subscription->plan, // Menggunakan accessor getPlanAttribute
                'status'            => $subscription->status,
                'max_employees'     => $subscription->max_employees,
                'max_vehicles'      => $subscription->max_vehicles,
                'started_at'        => $subscription->started_at->toDateTimeString(),
                'expired_at'        => $subscription->expired_at->toDateTimeString(),
                'days_remaining'    => $subscription->daysRemaining(),
                'is_active'         => $subscription->isActive(),
                'ai_credits_limit'  => $subscription->ai_credits_limit,
                'ai_credits_used'   => $subscription->ai_credits_used,
                'ai_credits_remaining' => $subscription->aiCreditsRemaining(),
            ],
            'usage'             => [
                'employees'         => [
                    'current'   => $currentEmployees,
                    'max'       => $subscription->max_employees,
                    'remaining' => max(0, $subscription->max_employees - $currentEmployees),
                ],
                'vehicles'          => [
                    'current'   => $currentVehicles,
                    'max'       => $subscription->max_vehicles,
                    'remaining' => max(0, $subscription->max_vehicles - $currentVehicles),
                ],
            ],
        ]);
    }
}
