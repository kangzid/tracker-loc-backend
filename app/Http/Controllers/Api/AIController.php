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
            case 'update_attendance':
                return $this->updateAttendance($request, $user->id);
            case 'get_analytics':
                return $this->getAnalytics($request, $user->id);
            case 'check_tasks':
                return $this->checkTasks($request, $user->id);
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
        $query = Employee::where('admin_id', $adminId)->with('user:id,name,is_active');

        if ($name) {
            $query->whereHas('user', function ($q) use ($name) {
                $q->where('name', 'like', "%{$name}%");
            });
        }

        $totalCount = $query->count();

        $employees = $query->select('id', 'user_id', 'employee_id', 'department', 'position', 'latitude', 'longitude')
            ->limit(10)
            ->get()
            ->map(function ($emp) {
            return [
                'name' => $emp->user?->name ?? 'Unknown',
                'id' => $emp->employee_id,
                'department' => $emp->department,
                'position' => $emp->position,
                'status' => ($emp->user?->is_active ?? false) ? 'Aktif' : 'Nonaktif',
                'last_location' => $emp->latitude ? "{$emp->latitude}, {$emp->longitude}" : 'Belum ada data'
            ];
        });

        return response()->json([
            'total_karyawan' => $totalCount,
            'menampilkan_maksimal' => 10,
            'data' => $employees
        ]);
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

        $totalCount = $query->count();

        $vehicles = $query->select('id', 'vehicle_number', 'vehicle_type', 'brand', 'model', 'is_active', 'latitude', 'longitude')
            ->limit(10)
            ->get()
            ->map(function ($v) {
            return [
                'plate' => $v->vehicle_number,
                'type' => $v->vehicle_type,
                'brand_model' => "{$v->brand} {$v->model}",
                'status' => $v->is_active ? 'Aktif' : 'Nonaktif',
                'last_location' => $v->latitude ? "{$v->latitude}, {$v->longitude}" : 'Belum ada data'
            ];
        });

        return response()->json([
            'total_kendaraan' => $totalCount,
            'menampilkan_maksimal' => 10,
            'data' => $vehicles
        ]);
    }

    private function checkAttendance(Request $request, $adminId)
    {
        $name = $request->query('name');
        if (!$name) return response()->json(['error' => 'Nama karyawan diperlukan'], 400);

        $employee = Employee::where('admin_id', $adminId)
            ->whereHas('user', function ($q) use ($name) {
                $q->where('name', 'like', "%{$name}%");
            })
            ->with('user:id,name')
            ->select('id', 'user_id')
            ->first();

        if (!$employee) return response()->json(['error' => 'Karyawan tidak ditemukan'], 404);

        $attendance = Attendance::where('employee_id', $employee->id)
            ->whereDate('date', Carbon::today())
            ->select('id', 'employee_id', 'status', 'check_in', 'check_out', 'notes')
            ->first();

        if (!$attendance) {
            $empName = $employee->user?->name ?? 'Unknown';
            return response()->json([
                'name' => $empName,
                'status' => 'Belum Absen',
                'message' => "Hingga saat ini, {$empName} belum melakukan absensi masuk."
            ]);
        }

        return response()->json([
            'name' => $employee->user?->name ?? 'Unknown',
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
                'subject' => $employee->user?->name ?? 'Unknown',
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

    private function checkTasks(Request $request, $adminId)
    {
        $name = $request->query('name');
        if (!$name) return response()->json(['error' => 'Nama karyawan diperlukan'], 400);

        $employee = Employee::where('admin_id', $adminId)
            ->whereHas('user', function ($q) use ($name) {
                $q->where('name', 'like', "%{$name}%");
            })
            ->with('user:id,name')
            ->select('id', 'user_id')
            ->first();

        if (!$employee) return response()->json(['error' => 'Karyawan tidak ditemukan'], 404);

        $tasks = Task::where('assigned_to', $employee->id)
            ->whereIn('status', ['pending', 'in_progress'])
            ->select('id', 'title', 'status', 'priority', 'due_date')
            ->latest()
            ->limit(5)
            ->get();

        if ($tasks->isEmpty()) {
            return response()->json([
                'name' => $employee->user?->name ?? 'Unknown',
                'message' => 'Tidak ada tugas yang sedang berjalan atau pending untuk karyawan ini.'
            ]);
        }

        $taskList = $tasks->map(function ($task) {
            return [
                'title' => $task->title,
                'status' => $task->status,
                'priority' => $task->priority ?? 'normal',
                'due_date' => $task->due_date ? Carbon::parse($task->due_date)->format('d M Y') : 'Tanpa tenggat waktu'
            ];
        });

        return response()->json([
            'name' => $employee->user?->name ?? 'Unknown',
            'total_tugas_aktif' => $tasks->count(),
            'data' => $taskList
        ]);
    }
    private function updateAttendance(Request $request, $adminId)
    {
        $name = $request->query('name');
        $status = $request->query('status'); // 'present' or 'absent'

        if (!$name || !$status) return response()->json(['error' => 'Nama dan status diperlukan'], 400);
        if (!in_array($status, ['present', 'absent'])) return response()->json(['error' => 'Status tidak valid'], 400);

        $employee = Employee::where('admin_id', $adminId)
            ->whereHas('user', function ($q) use ($name) {
                $q->where('name', 'like', "%{$name}%");
            })
            ->with('user:id,name')
            ->select('id', 'user_id', 'admin_id')
            ->first();

        if (!$employee) return response()->json(['error' => 'Karyawan tidak ditemukan'], 404);

        $attendance = Attendance::firstOrCreate(
            ['employee_id' => $employee->id, 'date' => Carbon::today()],
            ['admin_id' => $adminId]
        );

        $attendance->status = $status;
        if ($status === 'present' && !$attendance->check_in) {
            $attendance->check_in = Carbon::now()->format('H:i:00');
        }
        $attendance->save();

        return response()->json([
            'success' => true,
            'name' => $employee->user?->name ?? 'Unknown',
            'message' => "Status absensi " . ($employee->user?->name ?? 'karyawan') . " hari ini telah diubah menjadi " . ($status == 'present' ? 'Hadir (Present)' : 'Absent')
        ]);
    }
}
