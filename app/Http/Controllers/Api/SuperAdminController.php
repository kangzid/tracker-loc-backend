<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\Subscription;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

/**
 * SuperAdminController
 *
 * Semua endpoint di controller ini hanya bisa diakses oleh user dengan role 'superadmin'.
 * Dilindungi oleh dua middleware: auth:sanctum + superadmin.
 */
class SuperAdminController extends Controller
{
    // ===========================================================
    // DASHBOARD
    // ===========================================================

    /**
     * GET /api/superadmin/dashboard
     * Ringkasan statistik keseluruhan platform.
     */
    public function dashboard()
    {
        $totalAdmins = User::where('role', 'admin')->count();
        $activeAdmins = User::where('role', 'admin')->where('is_active', true)->count();
        $totalEmployees = Employee::count();
        $totalVehicles = Vehicle::count();
        $activeSubs = Subscription::where('status', 'active')->whereDate('expired_at', '>=', now())->count();
        $expiredSubs = Subscription::where('status', 'expired')->orWhereDate('expired_at', '<', now())->count();
        
        // Get plan ID for trial
        $trialPlanId = \App\Models\Plan::where('slug', 'trial')->value('id');
        $trialSubs = $trialPlanId ? Subscription::where('plan_id', $trialPlanId)->where('status', 'active')->count() : 0;

        // Statistik Pendapatan (Bulan Ini)
        $currentMonthRevenue = \App\Models\Transaction::where('payment_status', 'settlement')
            ->whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year)
            ->sum('final_price');

        // Data Grafik Pendapatan (14 hari terakhir)
        $revenueChart = [];
        for ($i = 13; $i >= 0; $i--) {
            $date = now()->subDays($i)->format('Y-m-d');
            $revenueChart[] = [
                'date' => now()->subDays($i)->format('d M'),
                'amount' => \App\Models\Transaction::where('payment_status', 'settlement')
                    ->whereDate('created_at', $date)
                    ->sum('final_price')
            ];
        }

        // Statistik Pemakaian Sistem (Tenant Baru per hari - 14 hari terakhir)
        $usageChart = [];
        for ($i = 13; $i >= 0; $i--) {
            $date = now()->subDays($i)->format('Y-m-d');
            $usageChart[] = [
                'date' => now()->subDays($i)->format('d M'),
                'count' => User::where('role', 'admin')
                    ->whereDate('created_at', $date)
                    ->count()
            ];
        }

        return response()->json([
            'summary' => [
                'total_admins' => $totalAdmins,
                'active_admins' => $activeAdmins,
                'total_employees' => $totalEmployees,
                'total_vehicles' => $totalVehicles,
                'monthly_revenue' => (int)$currentMonthRevenue,
            ],
            'subscriptions' => [
                'active' => $activeSubs,
                'expired' => $expiredSubs,
                'trial' => $trialSubs,
            ],
            'charts' => [
                'revenue' => $revenueChart,
                'usage' => $usageChart,
            ]
        ]);
    }

    // ===========================================================
    // MANAJEMEN AKUN ADMIN (TENANT)
    // ===========================================================

    /**
     * GET /api/superadmin/admins
     * Daftar semua akun Admin beserta info subscription-nya.
     */
    public function listAdmins(Request $request)
    {
        $perPage = $request->query('per_page', 15);
        $search = $request->query('search');

        $query = User::where('role', 'admin')
            ->with([
                'subscription' => function ($q) {
                    $q->latest();
                }
            ]);

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        $admins = $query->orderBy('created_at', 'desc')->paginate($perPage);

        // Tambahkan usage info per admin
        $admins->getCollection()->transform(function ($admin) {
            $sub = $admin->subscription;
            $admin->subscription_status = $sub ? [
                'plan' => $sub->plan,
                'status' => $sub->status,
                'expired_at' => $sub->expired_at?->toDateTimeString(),
                'days_remaining' => $sub->daysRemaining(),
                'is_active' => $sub->isActive(),
            ] : null;
            return $admin;
        });

        return response()->json($admins);
    }

    /**
     * GET /api/superadmin/admins/{id}
     * Detail satu akun Admin lengkap: profil + subscription + employee/vehicle usage.
     */
    public function showAdmin($id)
    {
        $admin = User::where('role', 'admin')->with('subscription')->find($id);

        if (!$admin) {
            return response()->json(['message' => 'Admin tidak ditemukan.'], 404);
        }

        $sub = $admin->subscription;

        return response()->json([
            'admin' => $admin,
            'subscription' => $sub ? [
                'id' => $sub->id,
                'plan' => $sub->plan,
                'status' => $sub->status,
                'company_name' => $sub->company_name,
                'contact_phone' => $sub->contact_phone,
                'max_employees' => $sub->max_employees,
                'max_vehicles' => $sub->max_vehicles,
                'started_at' => $sub->started_at->toDateTimeString(),
                'expired_at' => $sub->expired_at->toDateTimeString(),
                'days_remaining' => $sub->daysRemaining(),
                'is_active' => $sub->isActive(),
            ] : null,
        ]);
    }

    /**
     * POST /api/superadmin/admins
     * Buat akun Admin baru secara manual (oleh superadmin), beserta subscription-nya.
     */
    public function createAdmin(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'company_name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'contact_phone' => 'nullable|string|max:20',
            'plan' => 'required|in:trial,monthly,yearly',
            'max_employees' => 'required|integer|min:1',
            'max_vehicles' => 'required|integer|min:1',
            'duration_days' => 'required|integer|min:1',
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => 'Validasi gagal.', 'errors' => $validator->errors()], 422);
        }

        $plainPassword = Str::password(12, letters: true, numbers: true, symbols: false, spaces: false);

        $user = User::create([
            'name' => 'Admin ' . $request->company_name,
            'email' => $request->email,
            'password' => Hash::make($plainPassword),
            'role' => 'admin',
            'is_active' => true,
        ]);

        $subscription = Subscription::create([
            'user_id' => $user->id,
            'plan' => $request->plan,
            'max_employees' => $request->max_employees,
            'max_vehicles' => $request->max_vehicles,
            'company_name' => $request->company_name,
            'contact_phone' => $request->contact_phone,
            'started_at' => now(),
            'expired_at' => now()->addDays($request->duration_days),
            'status' => 'active',
        ]);

        return response()->json([
            'message' => 'Akun Admin berhasil dibuat.',
            'admin' => $user,
            'subscription' => $subscription,
            'credentials' => [
                'email' => $user->email,
                'password' => $plainPassword,
            ],
        ], 201);
    }

    /**
     * PUT /api/superadmin/admins/{id}/toggle
     * Aktifkan atau nonaktifkan akun Admin (suspend).
     */
    public function toggleAdmin($id)
    {
        $admin = User::where('role', 'admin')->find($id);

        if (!$admin) {
            return response()->json(['message' => 'Admin tidak ditemukan.'], 404);
        }

        $admin->update(['is_active' => !$admin->is_active]);

        return response()->json([
            'message' => 'Status akun berhasil diperbarui.',
            'is_active' => $admin->is_active,
            'admin' => $admin,
        ]);
    }

    /**
     * PUT /api/superadmin/admins/{id}/reset-password
     * Superadmin mengganti password admin tanpa OTP.
     */
    public function resetAdminPassword(Request $request, $id)
    {
        $admin = User::where('role', 'admin')->find($id);

        if (!$admin) {
            return response()->json(['message' => 'Admin tidak ditemukan.'], 404);
        }

        $validator = Validator::make($request->all(), [
            'password' => 'required|min:8',
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => 'Validasi gagal.', 'errors' => $validator->errors()], 422);
        }

        $admin->update([
            'password' => Hash::make($request->password)
        ]);

        return response()->json([
            'message' => 'Password admin berhasil direset.'
        ]);
    }

    /**
     * DELETE /api/superadmin/admins/{id}
     * Hapus akun Admin beserta SEMUA data terkait secara permanen:
     * - Subscription
     * - Employees (cascade akan hapus attendance, tasks, locations, dll)
     * - Vehicles
     * - Notifications
     */
    public function deleteAdmin($id)
    {
        $admin = User::where('role', 'admin')->find($id);

        if (!$admin) {
            return response()->json(['message' => 'Admin tidak ditemukan.'], 404);
        }

        $adminEmail = $admin->email;

        try {
            // Get all employees of this admin for cascade delete
            $employees = Employee::where('admin_id', $admin->id)->get();
            $employeeIds = $employees->pluck('id')->toArray();
            $employeeUserIds = $employees->pluck('user_id')->filter()->toArray(); // Get user IDs

            // Cascade delete untuk semua employees
            if (!empty($employeeIds)) {
                // Delete attendance records (employee_id column)
                \App\Models\Attendance::whereIn('employee_id', $employeeIds)->delete();

                // Delete tasks (assigned_to column references employees)
                \App\Models\Task::whereIn('assigned_to', $employeeIds)->delete();

                // Delete locations (polymorphic: trackable_id + trackable_type)
                \App\Models\Location::where('trackable_type', 'App\Models\Employee')
                    ->whereIn('trackable_id', $employeeIds)
                    ->delete();

                // Delete notifications (employee_id column)
                \App\Models\Notification::whereIn('employee_id', $employeeIds)->delete();

                // Delete employees
                Employee::whereIn('id', $employeeIds)->delete();

                // Delete user accounts of these employees
                if (!empty($employeeUserIds)) {
                    User::whereIn('id', $employeeUserIds)->where('role', 'employee')->delete();
                }
            }

            // Delete vehicles (use admin_id, not user_id)
            // Also delete vehicle locations (polymorphic)
            $vehicles = Vehicle::where('admin_id', $admin->id)->get();
            $vehicleIds = $vehicles->pluck('id')->toArray();
            if (!empty($vehicleIds)) {
                \App\Models\Location::where('trackable_type', 'App\Models\Vehicle')
                    ->whereIn('trackable_id', $vehicleIds)
                    ->delete();
            }
            Vehicle::where('admin_id', $admin->id)->delete();

            // Delete geofences
            \App\Models\Geofence::where('admin_id', $admin->id)->delete();

            // Delete notifications created by this admin (broadcast)
            \App\Models\Notification::where('created_by', $admin->id)->delete();

            // Delete admin notification status
            \App\Models\AdminNotificationStatus::where('admin_id', $admin->id)->delete();

            // Delete subscription (use user_id since subscription is tied to users)
            Subscription::where('user_id', $admin->id)->delete();

            // Delete the admin user itself
            $admin->delete();

            return response()->json([
                'message' => "Akun Admin ({$adminEmail}) dan SEMUA data terkait berhasil dihapus (Employees: " . count($employeeIds) . " + User accounts: " . count($employeeUserIds) . ", Vehicles: " . count($vehicleIds) . ", Subscriptions, Notifications, dll).",
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error saat menghapus admin',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // ===========================================================
    // MANAJEMEN SUBSCRIPTION & KUOTA
    // ===========================================================

    /**
     * GET /api/superadmin/subscriptions
     * Daftar semua subscription di platform.
     */
    public function listSubscriptions(Request $request)
    {
        $status = $request->query('status');  // active, expired, cancelled
        $perPage = $request->query('per_page', 15);

        $query = Subscription::with(['user', 'planDetails']);

        if ($status) {
            $query->where('status', $status);
        }

        $subs = $query->orderBy('created_at', 'desc')->paginate($perPage);

        return response()->json($subs);
    }

    /**
     * PUT /api/superadmin/subscriptions/{id}
     * Update kuota, plan, atau perpanjang masa berlaku subscription tertentu.
     */
    public function updateSubscription(Request $request, $id)
    {
        $subscription = Subscription::find($id);

        if (!$subscription) {
            return response()->json(['message' => 'Subscription tidak ditemukan.'], 404);
        }

        $validator = Validator::make($request->all(), [
            'plan' => 'sometimes|string|exists:plans,slug',
            'max_employees' => 'sometimes|integer|min:1',
            'max_vehicles' => 'sometimes|integer|min:1',
            'expired_at' => 'sometimes|date',
            'ai_credits_limit' => 'sometimes|integer|min:0',
            'status' => 'sometimes|in:active,inactive,expired,cancelled,pending',
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => 'Validasi gagal.', 'errors' => $validator->errors()], 422);
        }

        $updateData = $request->only(['max_employees', 'max_vehicles', 'expired_at', 'status', 'ai_credits_limit']);
        
        // Reset AI usage if requested
        if ($request->boolean('reset_ai_usage')) {
            $updateData['ai_credits_used'] = 0;
        }

        // Apply credit adjustment if provided
        if ($request->has('adjustment')) {
            $updateData['ai_credits_limit'] = $subscription->ai_credits_limit + (int)$request->adjustment;
        }
        
        // If plan is changed, also sync plan_id and credits
        if ($request->has('plan')) {
            $plan = \App\Models\Plan::where('slug', $request->plan)->first();
            if ($plan) {
                $updateData['plan_id'] = $plan->id;
                // If max_employees/max_vehicles not provided manually, use from plan
                if (!$request->has('max_employees')) $updateData['max_employees'] = $plan->max_employees;
                if (!$request->has('max_vehicles')) $updateData['max_vehicles'] = $plan->max_vehicles;
                $updateData['ai_credits_limit'] = $plan->ai_credits;
            }
        }

        $subscription->update($updateData);

        return response()->json([
            'message' => 'Subscription berhasil diperbarui.',
            'subscription' => $subscription->fresh(),
        ]);
    }

    /**
     * POST /api/superadmin/subscriptions/{id}/extend
     * Perpanjang masa berlaku subscription dengan menambah jumlah hari tertentu.
     */
    public function extendSubscription(Request $request, $id)
    {
        $subscription = Subscription::find($id);

        if (!$subscription) {
            return response()->json(['message' => 'Subscription tidak ditemukan.'], 404);
        }

        $validator = Validator::make($request->all(), [
            'days' => 'required|integer|min:1|max:3650',
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => 'Validasi gagal.', 'errors' => $validator->errors()], 422);
        }

        // Jika sudah expired, hitung dari sekarang. Jika masih aktif, tambah dari expired_at existing.
        $baseDate = $subscription->expired_at->isPast() ? now() : $subscription->expired_at;
        $newExpiry = $baseDate->addDays($request->days);

        $subscription->update([
            'expired_at' => $newExpiry,
            'status' => 'active',
        ]);

        return response()->json([
            'message' => "Subscription diperpanjang {$request->days} hari.",
            'new_expired_at' => $subscription->fresh()->expired_at->toDateTimeString(),
            'days_remaining' => $subscription->fresh()->daysRemaining(),
        ]);
    }

    /**
     * DELETE /api/superadmin/subscriptions/{id}
     * Batalkan (cancel) subscription — akun admin tidak dihapus, hanya aksesnya dicabut.
     */
    public function cancelSubscription($id)
    {
        $subscription = Subscription::find($id);

        if (!$subscription) {
            return response()->json(['message' => 'Subscription tidak ditemukan.'], 404);
        }

        $subscription->update(['status' => 'cancelled']);

        return response()->json([
            'message' => 'Subscription berhasil dibatalkan. Akun Admin masih ada tapi aksesnya dicabut.',
        ]);
    }
}
