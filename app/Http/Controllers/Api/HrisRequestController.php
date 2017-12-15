<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\HrisRequest;
use App\Models\HrisLeaveType;
use App\Models\HrisEmployeeLeaveBalance;
use App\Models\Employee;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;

class HrisRequestController extends Controller
{
    private function getTenantId(Request $request)
    {
        $user = $request->user();
        if ($user->isAdmin()) {
            return $user->id;
        }
        if ($user->role === 'employee' && $user->admin_id) {
            return $user->admin_id;
        }
        return $user->id;
    }

    public function index(Request $request)
    {
        $tenantId = $this->getTenantId($request);
        $query = HrisRequest::where('tenant_id', $tenantId)
            ->with(['employee.user', 'leaveType', 'approver']);

        if ($request->filled('type')) {
            $query->where('request_type', $request->type);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
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

        if ($request->filled('start_date')) {
            $query->whereDate('start_date', '>=', $request->start_date);
        }

        if ($request->filled('end_date')) {
            $query->whereDate('start_date', '<=', $request->end_date);
        }

        $items = $query->orderBy('id', 'desc')->get();
        return response()->json($items);
    }

    public function store(Request $request)
    {
        $tenantId = $this->getTenantId($request);

        $validator = Validator::make($request->all(), [
            'employee_id' => 'required|exists:employees,id',
            'request_type' => 'required|in:izin_absen,izin_sakit,izin_cuti,izin_dinas,ajuan_jadwal',
            'start_date' => 'required|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'days_count' => 'nullable|integer|min:1',
            'reason' => 'nullable|string|max:1000',
            'leave_type_id' => 'nullable|exists:hris_leave_types,id',
            'attachment_base64' => 'nullable|string',
            'attachment_name' => 'nullable|string|max:255',
            'from_shift' => 'nullable|string|max:100',
            'to_shift' => 'nullable|string|max:100',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $startDate = Carbon::parse($request->start_date);
        $endDate = $request->end_date ? Carbon::parse($request->end_date) : $startDate;
        $daysCount = $request->days_count ?? ($startDate->diffInDays($endDate) + 1);

        // Generate Prefix Code
        $prefixMap = [
            'izin_absen' => 'IA',
            'izin_sakit' => 'IS',
            'izin_cuti' => 'IC',
            'izin_dinas' => 'ID',
            'ajuan_jadwal' => 'AJ',
        ];
        $prefix = $prefixMap[$request->request_type] ?? 'REQ';
        $yearMonth = $startDate->format('ym'); // e.g. 2608

        $count = HrisRequest::where('tenant_id', $tenantId)
            ->where('request_type', $request->request_type)
            ->where('code', 'like', "{$prefix}{$yearMonth}%")
            ->count() + 1;
        $code = sprintf('%s%s%04d', $prefix, $yearMonth, $count);

        $record = HrisRequest::create([
            'tenant_id' => $tenantId,
            'employee_id' => $request->employee_id,
            'request_type' => $request->request_type,
            'code' => $code,
            'leave_type_id' => $request->leave_type_id,
            'start_date' => $startDate->format('Y-m-d'),
            'end_date' => $endDate->format('Y-m-d'),
            'days_count' => $daysCount,
            'reason' => $request->reason,
            'attachment_base64' => $request->attachment_base64,
            'attachment_name' => $request->attachment_name,
            'from_shift' => $request->from_shift,
            'to_shift' => $request->to_shift,
            'status' => 'pending',
        ]);

        return response()->json($record->load(['employee.user', 'leaveType']), 201);
    }

    public function approve(Request $request, $id)
    {
        $tenantId = $this->getTenantId($request);
        $record = HrisRequest::where('tenant_id', $tenantId)->findOrFail($id);

        $record->status = 'approved';
        $record->approved_by = $request->user()->id;
        $record->approved_at = now();
        $record->approver_note = $request->note ?? 'Disetujui oleh admin';
        $record->save();

        // Update leave balance if leave request
        if ($record->request_type === 'izin_cuti' && $record->leave_type_id) {
            $year = Carbon::parse($record->start_date)->year;
            $balance = HrisEmployeeLeaveBalance::firstOrCreate(
                [
                    'tenant_id' => $tenantId,
                    'employee_id' => $record->employee_id,
                    'leave_type_id' => $record->leave_type_id,
                    'year' => $year,
                ],
                [
                    'quota' => 12,
                    'used' => 0,
                    'remaining' => 12,
                ]
            );

            $balance->used += $record->days_count;
            $balance->remaining = max(0, $balance->quota - $balance->used);
            $balance->save();
        }

        return response()->json([
            'message' => 'Pengajuan berhasil disetujui.',
            'data' => $record->load(['employee.user', 'leaveType', 'approver'])
        ]);
    }

    public function reject(Request $request, $id)
    {
        $tenantId = $this->getTenantId($request);
        $record = HrisRequest::where('tenant_id', $tenantId)->findOrFail($id);

        $record->status = 'rejected';
        $record->approved_by = $request->user()->id;
        $record->approved_at = now();
        $record->approver_note = $request->note ?? 'Ditolak';
        $record->save();

        return response()->json([
            'message' => 'Pengajuan berhasil ditolak.',
            'data' => $record->load(['employee.user', 'leaveType', 'approver'])
        ]);
    }

    public function destroy(Request $request, $id)
    {
        $tenantId = $this->getTenantId($request);
        $record = HrisRequest::where('tenant_id', $tenantId)->findOrFail($id);
        $record->delete();

        return response()->json(['message' => 'Data pengajuan berhasil dihapus.']);
    }
}
