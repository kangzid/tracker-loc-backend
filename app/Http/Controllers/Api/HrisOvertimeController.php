<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\HrisOvertime;
use App\Models\HrisOvertimeSetting;
use App\Models\Employee;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;

class HrisOvertimeController extends Controller
{
    private function getTenantId(Request $request)
    {
        $user = $request->user();
        return $user->role === 'employee' ? ($user->employee ? $user->employee->admin_id : ($user->admin_id ?? $user->id)) : $user->id;
    }

    public function index(Request $request)
    {
        $tenantId = $this->getTenantId($request);
        $query = HrisOvertime::where('tenant_id', $tenantId)
            ->with(['employee.user', 'approver']);

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('month')) {
            $query->whereMonth('date', $request->month);
        }

        if ($request->filled('year')) {
            $query->whereYear('date', $request->year);
        }

        if ($request->filled('start_date')) {
            $query->whereDate('date', '>=', $request->start_date);
        }

        if ($request->filled('end_date')) {
            $query->whereDate('date', '<=', $request->end_date);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->whereHas('employee', function ($q) use ($search) {
                $q->where('employee_id', 'like', "%{$search}%")
                  ->orWhereHas('user', function ($uq) use ($search) {
                      $uq->where('name', 'like', "%{$search}%");
                  });
            });
        }

        if ($request->filled('position')) {
            $query->whereHas('employee', fn($q) => $q->where('position', $request->position));
        }

        if ($request->filled('department')) {
            $query->whereHas('employee', fn($q) => $q->where('department', $request->department));
        }

        $items = $query->orderBy('date', 'desc')->orderBy('id', 'desc')->get();
        return response()->json($items);
    }

    public function summary(Request $request)
    {
        $tenantId = $this->getTenantId($request);
        $month = $request->month ?? date('m');
        $year = $request->year ?? date('Y');

        $query = HrisOvertime::where('tenant_id', $tenantId)
            ->whereMonth('date', $month)
            ->whereYear('date', $year);

        $totalHours = (float) (clone $query)->where('status', 'approved')->sum('duration_hours');
        $totalEstimatedPayout = (float) (clone $query)->where('status', 'approved')->sum('total_pay');
        $pendingCount = (int) (clone $query)->where('status', 'pending')->count();
        $approvedCount = (int) (clone $query)->where('status', 'approved')->count();
        $rejectedCount = (int) (clone $query)->where('status', 'rejected')->count();

        return response()->json([
            'month' => (int)$month,
            'year' => (int)$year,
            'total_hours' => $totalHours,
            'total_estimated_payout' => $totalEstimatedPayout,
            'pending_count' => $pendingCount,
            'approved_count' => $approvedCount,
            'rejected_count' => $rejectedCount,
        ]);
    }

    public function store(Request $request)
    {
        $tenantId = $this->getTenantId($request);

        $validator = Validator::make($request->all(), [
            'employee_id' => 'required|exists:employees,id',
            'date' => 'required|date',
            'start_time' => 'required',
            'end_time' => 'required',
            'reason' => 'required|string|max:1000',
            'rate_per_hour' => 'nullable|numeric|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // 1 Overtime per employee per day constraint
        $existing = HrisOvertime::where('tenant_id', $tenantId)
            ->where('employee_id', $request->employee_id)
            ->whereDate('date', $request->date)
            ->whereIn('status', ['pending', 'approved'])
            ->first();

        if ($existing) {
            $dateFormatted = date('d/m/Y', strtotime($request->date));
            return response()->json([
                'message' => "Pengajuan lembur pada tanggal {$dateFormatted} sudah ada. Setiap karyawan hanya dapat memiliki 1 pengajuan lembur per hari."
            ], 422);
        }


        // Get tenant overtime setting
        $setting = HrisOvertimeSetting::firstOrCreate(
            ['tenant_id' => $tenantId],
            ['default_rate_per_hour' => 25000, 'calculation_type' => 'flat', 'min_duration_minutes' => 30]
        );

        $startTime = Carbon::parse($request->date . ' ' . $request->start_time);
        $endTime = Carbon::parse($request->date . ' ' . $request->end_time);

        // If end_time is earlier than start_time, assume it ended next day
        if ($endTime->lt($startTime)) {
            $endTime->addDay();
        }

        $durationMinutes = $startTime->diffInMinutes($endTime);
        $durationHours = round($durationMinutes / 60, 2);

        $ratePerHour = $request->filled('rate_per_hour') && $request->rate_per_hour > 0 
            ? (float)$request->rate_per_hour 
            : (float)$setting->default_rate_per_hour;

        $totalPay = round($durationHours * $ratePerHour, 2);

        $status = $setting->auto_approve ? 'approved' : 'pending';

        $overtime = HrisOvertime::create([
            'tenant_id' => $tenantId,
            'employee_id' => $request->employee_id,
            'date' => $request->date,
            'start_time' => $startTime->format('H:i:s'),
            'end_time' => $endTime->format('H:i:s'),
            'duration_hours' => $durationHours,
            'reason' => $request->reason,
            'rate_per_hour' => $ratePerHour,
            'total_pay' => $totalPay,
            'status' => $status,
            'approved_by' => $status === 'approved' ? $request->user()->id : null,
            'approved_at' => $status === 'approved' ? now() : null,
        ]);

        return response()->json($overtime->load(['employee.user', 'approver']), 201);
    }

    public function approve(Request $request, $id)
    {
        $tenantId = $this->getTenantId($request);
        $overtime = HrisOvertime::where('tenant_id', $tenantId)->findOrFail($id);

        if ($request->filled('rate_per_hour')) {
            $overtime->rate_per_hour = (float)$request->rate_per_hour;
            $overtime->total_pay = round($overtime->duration_hours * $overtime->rate_per_hour, 2);
        }

        $overtime->status = 'approved';
        $overtime->approved_by = $request->user()->id;
        $overtime->approved_at = now();
        $overtime->approver_note = $request->note ?? 'Disetujui oleh admin';
        $overtime->save();

        return response()->json([
            'message' => 'Lembur berhasil disetujui.',
            'data' => $overtime->load(['employee.user', 'approver'])
        ]);
    }

    public function reject(Request $request, $id)
    {
        $tenantId = $this->getTenantId($request);
        $overtime = HrisOvertime::where('tenant_id', $tenantId)->findOrFail($id);

        $overtime->status = 'rejected';
        $overtime->approved_by = $request->user()->id;
        $overtime->approved_at = now();
        $overtime->approver_note = $request->note ?? 'Ditolak';
        $overtime->save();

        return response()->json([
            'message' => 'Pengajuan lembur ditolak.',
            'data' => $overtime->load(['employee.user', 'approver'])
        ]);
    }

    public function destroy(Request $request, $id)
    {
        $tenantId = $this->getTenantId($request);
        $overtime = HrisOvertime::where('tenant_id', $tenantId)->findOrFail($id);
        $overtime->delete();

        return response()->json(['message' => 'Data lembur berhasil dihapus.']);
    }

    public function getSettings(Request $request)
    {
        $tenantId = $this->getTenantId($request);
        $setting = HrisOvertimeSetting::firstOrCreate(
            ['tenant_id' => $tenantId],
            [
                'default_rate_per_hour' => 25000,
                'calculation_type' => 'flat',
                'min_duration_minutes' => 30,
                'auto_approve' => false
            ]
        );

        return response()->json($setting);
    }

    public function saveSettings(Request $request)
    {
        $tenantId = $this->getTenantId($request);

        $validator = Validator::make($request->all(), [
            'default_rate_per_hour' => 'required|numeric|min:0',
            'calculation_type' => 'required|in:flat,formula_depnaker',
            'min_duration_minutes' => 'required|integer|min:0',
            'auto_approve' => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $setting = HrisOvertimeSetting::updateOrCreate(
            ['tenant_id' => $tenantId],
            [
                'default_rate_per_hour' => $request->default_rate_per_hour,
                'calculation_type' => $request->calculation_type,
                'min_duration_minutes' => $request->min_duration_minutes,
                'auto_approve' => $request->auto_approve ?? false,
            ]
        );

        return response()->json($setting);
    }
}
