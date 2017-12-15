<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\Vehicle;
use App\Models\Attendance;
use App\Models\Task;
use Illuminate\Http\Request;
use Carbon\Carbon;

class AIController extends Controller
{
    /**
     * Endpoint sentral untuk query AI.
     * Menerima parameter 'action' untuk menentukan jenis data yang diambil.
     */
    public function query(Request $request)
    {
        $action = $request->query('action');
        $user = $request->user();

        // Pastikan hanya admin yang bisa query data operasional
        if (!$user->isAdmin()) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        // Cek kredit AI (Opsional: Kita bisa membiarkan tool calling tetap jalan selama session chat masih aktif,
        // tapi sebaiknya tetap ada pengaman dasar di sini)
        $subscription = \App\Models\Subscription::where('user_id', $user->id)->latest()->first();
        if ($subscription && !$subscription->isActive()) {
            return response()->json(['error' => 'Subscription tidak aktif atau sudah expired.'], 403);
        }

        if ($subscription && $subscription->aiCreditsRemaining() < 1) {
            return response()->json(['error' => 'Kredit AI Anda telah habis.'], 403);
        }

        switch ($action) {
            case 'list_employees':
                return $this->listEmployees($request, $user->id);
            case 'list_vehicles':
                return $this->listVehicles($request, $user->id);
            case 'check_attendance':
                return $this->checkAttendance($request, $user->id);
            case 'get_analytics':
                return $this->getAnalytics($request, $user->id);
            default:
                return response()->json(['error' => 'Action tidak valid'], 400);
        }
    }

    /**
     * POST /api/ai/usage
     * Mencatat penggunaan kredit AI.
     */
    public function recordUsage(Request $request)
    {
        $user = $request->user();
        $subscription = \App\Models\Subscription::where('user_id', $user->id)->latest()->first();

        if (!$subscription) {
            return response()->json(['error' => 'Subscription tidak ditemukan'], 404);
        }

        $cost = $request->input('cost', 2); // Default 2 kredit per pertanyaan
        
        if ($subscription->aiCreditsRemaining() < $cost) {
            return response()->json(['error' => 'Kredit tidak cukup'], 403);
        }

        $subscription->increment('ai_credits_used', $cost);

        return response()->json([
            'success' => true,
            'credits_used' => $subscription->ai_credits_used,
            'credits_remaining' => $subscription->aiCreditsRemaining()
        ]);
    }

    private function listEmployees(Request $request, $adminId)
    {
        $name = $request->query('name');
        $query = Employee::where('admin_id', $adminId)->with('user');

        if ($name) {
            $query->whereHas('user', function ($q) use ($name) {
                $q->where('name', 'like', "%{$name}%");
            });
        }

        $employees = $query->limit(10)->get()->map(function ($emp) {
            return [
                'name' => $emp->user->name ?? 'Unknown',
                'id' => $emp->employee_id,
                'department' => $emp->department,
                'position' => $emp->position,
                'status' => ($emp->user->is_active ?? false) ? 'Aktif' : 'Nonaktif',
                'last_location' => $emp->latitude ? "{$emp->latitude}, {$emp->longitude}" : 'Belum ada data'
            ];
        });

        return response()->json($employees);
    }

    private function listVehicles(Request $request, $adminId)
    {
        $name = $request->query('name');
        $query = Vehicle::where('admin_id', $adminId);

        if ($name) {
            $query->where(function ($q) use ($name) {
                $q->where('vehicle_number', 'like', "%{$name}%")
                  ->orWhere('brand', 'like', "%{$name}%")
                  ->orWhere('model', 'like', "%{$name}%");
            });
        }

        $vehicles = $query->limit(10)->get()->map(function ($v) {
            return [
                'plate' => $v->vehicle_number,
                'type' => $v->vehicle_type,
                'brand_model' => "{$v->brand} {$v->model}",
                'status' => $v->is_active ? 'Aktif' : 'Nonaktif',
                'last_location' => $v->latitude ? "{$v->latitude}, {$v->longitude}" : 'Belum ada data'
            ];
        });

        return response()->json($vehicles);
    }

    private function checkAttendance(Request $request, $adminId)
    {
        $name = $request->query('name');
        if (!$name) return response()->json(['error' => 'Nama karyawan diperlukan'], 400);

        $employee = Employee::where('admin_id', $adminId)
            ->whereHas('user', function ($q) use ($name) {
                $q->where('name', 'like', "%{$name}%");
            })->first();

        if (!$employee) return response()->json(['error' => 'Karyawan tidak ditemukan'], 404);

        $attendance = Attendance::where('employee_id', $employee->id)
            ->whereDate('date', Carbon::today())
            ->first();

        if (!$attendance) {
            return response()->json([
                'name' => $employee->user->name,
                'status' => 'Belum Absen',
                'message' => "Hingga saat ini, {$employee->user->name} belum melakukan absensi masuk."
            ]);
        }

        return response()->json([
            'name' => $employee->user->name,
            'status' => $attendance->status,
            'check_in' => $attendance->check_in ? $attendance->check_in->format('H:i') : '-',
            'check_out' => $attendance->check_out ? $attendance->check_out->format('H:i') : 'Belum pulang',
            'notes' => $attendance->notes ?? '-'
        ]);
    }

    private function getAnalytics(Request $request, $adminId)
    {
        $type = $request->query('type');
        $name = $request->query('name');
        $months = (int) $request->query('months', 1);

        if ($type === 'employee') {
            $employee = Employee::where('admin_id', $adminId)
                ->whereHas('user', function ($q) use ($name) {
                    $q->where('name', 'like', "%{$name}%");
                })->first();

            if (!$employee) return response()->json(['error' => 'Karyawan tidak ditemukan'], 404);

            $totalAttendances = Attendance::where('employee_id', $employee->id)
                ->where('date', '>=', now()->subMonths($months))
                ->count();
            
            $tasksCompleted = Task::where('assigned_to', $employee->id)
                ->where('status', 'completed')
                ->where('updated_at', '>=', now()->subMonths($months))
                ->count();

            return response()->json([
                'subject' => $employee->user->name,
                'period' => "{$months} bulan terakhir",
                'total_absensi' => $totalAttendances,
                'tugas_selesai' => $tasksCompleted,
                'jarak_tempuh_estimasi' => 'Fitur kalkulasi jarak dalam pengembangan'
            ]);
        }

        if ($type === 'vehicle') {
            $vehicle = Vehicle::where('admin_id', $adminId)
                ->where('vehicle_number', 'like', "%{$name}%")
                ->first();

            if (!$vehicle) return response()->json(['error' => 'Kendaraan tidak ditemukan'], 404);

            return response()->json([
                'subject' => $vehicle->vehicle_number,
                'period' => "{$months} bulan terakhir",
                'status' => $vehicle->is_active ? 'Aktif' : 'Nonaktif',
                'jarak_tempuh_estimasi' => 'Fitur kalkulasi jarak dalam pengembangan'
            ]);
        }

        return response()->json(['error' => 'Tipe analytics tidak valid'], 400);
    }
}
