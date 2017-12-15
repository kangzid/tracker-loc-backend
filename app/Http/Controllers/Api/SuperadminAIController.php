<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Employee;
use App\Models\Vehicle;
use App\Models\Subscription;
use App\Models\Registration;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class SuperadminAIController extends Controller
{
    /**
     * Endpoint untuk Superadmin AI query data sistem.
     */
    public function query(Request $request)
    {
        $user = $request->user();
        $action = $request->query('action');

        if (!$user->isSuperAdmin()) {
            return response()->json(['error' => 'Akses ditolak. Ini adalah fitur khusus Superadmin.'], 403);
        }

        switch ($action) {
            case 'get_system_stats':
                return $this->getSystemStats();
            case 'get_financial_stats':
                return $this->getFinancialStats();
            case 'get_tenant_alerts':
                return $this->getTenantAlerts();
            case 'get_system_health':
                return $this->getSystemHealth();
            case 'adjust_tenant_credits':
                return $this->adjustTenantCredits($request);
            case 'search_tenant':
                return $this->searchTenant($request);
            default:
                return response()->json(['error' => 'Action tidak valid'], 400);
        }
    }

    private function searchTenant(Request $request)
    {
        $query = $request->input('query');
        $tenants = User::where('role', 'admin')
            ->where('name', 'like', "%$query%")
            ->with('subscription')
            ->get()
            ->map(function($user) {
                return [
                    'id' => $user->id,
                    'name' => $user->name,
                    'company' => $user->subscription->company_name ?? 'No Company',
                    'current_ai_limit' => $user->subscription->ai_credits_limit ?? 0,
                    'ai_used' => $user->subscription->ai_credits_used ?? 0
                ];
            });

        return response()->json(['tenants' => $tenants]);
    }

    private function adjustTenantCredits(Request $request)
    {
        $tenantId = $request->input('tenant_id');
        $adjustment = (int)$request->input('adjustment');

        $sub = Subscription::where('user_id', $tenantId)->first();

        if (!$sub) {
            return response()->json(['error' => 'Tenant tidak ditemukan atau tidak memiliki langganan aktif.'], 404);
        }

        $oldLimit = $sub->ai_credits_limit;
        $sub->ai_credits_limit += $adjustment;
        $sub->save();

        return response()->json([
            'message' => 'Kredit berhasil diperbarui.',
            'tenant_name' => $sub->user->name ?? 'Unknown',
            'old_limit' => $oldLimit,
            'new_limit' => $sub->ai_credits_limit,
            'adjustment' => $adjustment
        ]);
    }

    private function getSystemStats()
    {
        return response()->json([
            'total_tenants' => User::where('role', 'admin')->count(),
            'total_employees' => Employee::count(),
            'total_vehicles' => Vehicle::count(),
            'pending_registrations' => Registration::where('status', 'pending')->count(),
        ]);
    }

    private function getFinancialStats()
    {
        $startOfMonth = Carbon::now()->startOfMonth();
        
        $revenueThisMonth = \App\Models\Transaction::where('payment_status', 'settlement')
            ->where('created_at', '>=', $startOfMonth)
            ->sum('final_price');

        $totalRevenueAllTime = \App\Models\Transaction::where('payment_status', 'settlement')
            ->sum('final_price');

        return response()->json([
            'revenue_this_month' => "Rp " . number_format($revenueThisMonth, 0, ',', '.'),
            'total_revenue_all_time' => "Rp " . number_format($totalRevenueAllTime, 0, ',', '.'),
            'period' => $startOfMonth->format('F Y'),
            'transaction_count_this_month' => \App\Models\Transaction::where('payment_status', 'settlement')
                ->where('created_at', '>=', $startOfMonth)
                ->count()
        ]);
    }

    private function getTenantAlerts()
    {
        $expiringSoon = Subscription::where('status', 'active')
            ->where('expired_at', '<=', Carbon::now()->addDays(7))
            ->with('user')
            ->get()
            ->map(function($sub) {
                return [
                    'tenant_name' => $sub->user->name ?? 'Unknown',
                    'expires_at' => $sub->expired_at->format('d M Y'),
                    'days_left' => Carbon::now()->diffInDays($sub->expired_at, false)
                ];
            });

        return response()->json([
            'expiring_tenants' => $expiringSoon,
            'count' => $expiringSoon->count()
        ]);
    }

    private function getSystemHealth()
    {
        // Get database size from MySQL
        $dbName = env('DB_DATABASE');
        $dbSize = DB::select("
            SELECT ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) AS size 
            FROM information_schema.TABLES 
            WHERE table_schema = ?", [$dbName]);

        return response()->json([
            'database_size_mb' => $dbSize[0]->size . " MB",
            'database_name' => $dbName,
            'connection' => 'Healthy',
            'laravel_version' => app()->version(),
            'server_time' => now()->toDateTimeString(),
        ]);
    }
}
