<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\HrisRequest;
use App\Models\HrisEmployeeLeaveBalance;
use App\Models\Attendance;
use App\Services\EncryptedStorageService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;

class HrisRequestController extends Controller
{
    private function getTenantId(Request $request)
    {
        $user = $request->user();
        if ($user->role === 'employee') {
            return $user->employee->admin_id ?? $user->admin_id ?? 2;
        }
        return $user->id ?? 2;
    }

    private function normalizeRequestType(string $type): string
    {
        $map = [
            'cuti' => 'izin_cuti',
            'izin_cuti' => 'izin_cuti',
            'izin_khusus' => 'izin_cuti',
            'izin_sakit' => 'izin_sakit',
            'sakit' => 'izin_sakit',
            'izin_absen' => 'izin_absen',
            'izin' => 'izin_absen',
            'izin_dinas' => 'izin_dinas',
            'dinas_luar' => 'izin_dinas',
            'dinas' => 'izin_dinas',
            'ajuan_jadwal' => 'ajuan_jadwal',
        ];
        return $map[strtolower(trim($type))] ?? 'izin_absen';
    }

    public function summary(Request $request)
    {
        $tenantId = $this->getTenantId($request);
        $user = $request->user();
        $employeeId = $request->query('employee_id') ?? ($user->role === 'employee' ? ($user->employee ? $user->employee->id : null) : null);

        $baseQuery = HrisRequest::where('tenant_id', $tenantId);
        if ($employeeId) {
            $baseQuery->where('employee_id', $employeeId);
        }

        $pendingCount = (clone $baseQuery)->where('status', 'pending')->count();
        $approvedCount = (clone $baseQuery)->where('status', 'approved')->count();
        $rejectedCount = (clone $baseQuery)->where('status', 'rejected')->count();
        $totalDaysApproved = (int) (clone $baseQuery)->where('status', 'approved')->sum('days_count');

        $remainingLeave = 12;
        $usedLeave = 0;
        if ($employeeId) {
            $year = date('Y');
            $balance = HrisEmployeeLeaveBalance::where('tenant_id', $tenantId)
                ->where('employee_id', $employeeId)
                ->where('year', $year)
                ->first();

            if ($balance) {
                $remainingLeave = max(0, $balance->quota - $balance->used);
                $usedLeave = (int) $balance->used;
            } else {
                $usedLeave = (int) HrisRequest::where('tenant_id', $tenantId)
                    ->where('employee_id', $employeeId)
                    ->whereIn('request_type', ['izin_cuti', 'cuti'])
                    ->where('status', 'approved')
                    ->whereYear('start_date', $year)
                    ->sum('days_count');
                $remainingLeave = max(0, 12 - $usedLeave);
            }
        }

        return response()->json([
            'status' => 'success',
            'pending_count' => $pendingCount,
            'approved_count' => $approvedCount,
            'rejected_count' => $rejectedCount,
            'total_days_approved' => $totalDaysApproved,
            'remaining_leave' => $remainingLeave,
            'used_leave' => $usedLeave,
            'quota_leave' => 12
        ]);
    }

    public function index(Request $request)
    {
        $tenantId = $this->getTenantId($request);
        $user = $request->user();

        $query = HrisRequest::where('tenant_id', $tenantId)
            ->with(['employee.user', 'leaveType', 'approver'])
            ->orderBy('id', 'desc');

        if ($request->has('request_type') && $request->request_type !== 'all') {
            $normalized = $this->normalizeRequestType($request->request_type);
            $query->where('request_type', $normalized);
        }

        if ($request->has('status') && $request->status !== 'all') {
            $query->where('status', $request->status);
        }

        $employeeId = $request->query('employee_id') ?? ($user->role === 'employee' ? ($user->employee ? $user->employee->id : null) : null);
        if ($employeeId) {
            $query->where('employee_id', $employeeId);
        }

        return response()->json($query->paginate($request->get('per_page', 15)));
    }

    public function store(Request $request)
    {
        $tenantId = $this->getTenantId($request);
        $user = $request->user();

        $validator = Validator::make($request->all(), [
            'employee_id' => 'required|exists:employees,id',
            'request_type' => 'required|string',
            'leave_type_id' => 'nullable',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'days_count' => 'nullable|numeric',
            'total_days' => 'nullable|numeric',
            'reason' => 'required|string',
            'attachment_file' => 'nullable|file|max:10240',
            'attachment_base64' => 'nullable|string',
            'attachment_name' => 'nullable|string|max:255',
            'status' => 'nullable|in:pending,approved,rejected',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $normalizedType = $this->normalizeRequestType($request->request_type);

        // Generate Code: REQ[YY][MM][0001]
        $prefix = 'REQ' . date('ym', strtotime($request->start_date));
        $last = HrisRequest::where('tenant_id', $tenantId)
            ->where('code', 'like', "{$prefix}%")
            ->orderBy('id', 'desc')
            ->first();

        $nextNum = 1;
        if ($last && preg_match('/' . $prefix . '([0-9]+)/', $last->code, $matches)) {
            $nextNum = (int)$matches[1] + 1;
        }
        $code = $prefix . str_pad($nextNum, 4, '0', STR_PAD_LEFT);

        // Calculate days if not provided
        $start = strtotime($request->start_date);
        $end = strtotime($request->end_date);
        $diffDays = (int) round(abs($end - $start) / 86400) + 1;
        $daysCount = $request->days_count ?: ($request->total_days ?: $diffDays);

        $attachPath = null;
        $attachName = $request->attachment_name;
        if ($request->hasFile('attachment_file')) {
            $stored = EncryptedStorageService::storeEncrypted($request->file('attachment_file'), $tenantId, 'requests', 'req_' . $code);
            $attachPath = $stored['path'];
            $attachName = $stored['name'];
        } elseif ($request->filled('attachment_base64')) {
            $stored = EncryptedStorageService::storeEncrypted($request->attachment_base64, $tenantId, 'requests', 'req_' . $code, $attachName);
            $attachPath = $stored['path'];
            $attachName = $stored['name'];
        }

        // If created by Admin and marked approved (or direct approval)
        $status = $request->status ?: ($user && $user->isAdmin() ? 'approved' : 'pending');

        $hrisReq = HrisRequest::create([
            'tenant_id' => $tenantId,
            'employee_id' => $request->employee_id,
            'code' => $code,
            'request_type' => $normalizedType,
            'leave_type_id' => $request->leave_type_id ?: null,
            'start_date' => $request->start_date,
            'end_date' => $request->end_date,
            'days_count' => $daysCount,
            'reason' => $request->reason,
            'attachment_path' => $attachPath,
            'attachment_name' => $attachName,
            'status' => $status,
            'approved_by' => $status === 'approved' && $user ? $user->id : null,
            'approved_at' => $status === 'approved' ? now() : null,
            'approver_note' => $status === 'approved' ? 'Disetujui langsung oleh Admin' : null,
        ]);

        // Auto sync to attendance if approved
        if ($status === 'approved') {
            $this->syncApprovedRequestToAttendance($hrisReq, $tenantId);
            $this->deductLeaveQuota($hrisReq, $tenantId);
        }

        return response()->json($hrisReq->load(['employee.user', 'leaveType', 'approver']), 201);
    }

    public function previewAttachment(Request $request, $id)
    {
        $tenantId = $this->getTenantId($request);
        $hrisReq = HrisRequest::where('tenant_id', $tenantId)->findOrFail($id);
        if (!$hrisReq->attachment_path) {
            return response()->json(['message' => 'Berkas lampiran tidak ditemukan.'], 404);
        }
        return EncryptedStorageService::streamResponse($hrisReq->attachment_path, $hrisReq->attachment_name, false);
    }

    public function downloadAttachment(Request $request, $id)
    {
        $tenantId = $this->getTenantId($request);
        $hrisReq = HrisRequest::where('tenant_id', $tenantId)->findOrFail($id);
        if (!$hrisReq->attachment_path) {
            return response()->json(['message' => 'Berkas lampiran tidak ditemukan.'], 404);
        }
        return EncryptedStorageService::streamResponse($hrisReq->attachment_path, $hrisReq->attachment_name, true);
    }

    public function approve(Request $request, $id)
    {
        $tenantId = $this->getTenantId($request);
        $hrisReq = HrisRequest::where('tenant_id', $tenantId)->findOrFail($id);

        $hrisReq->status = 'approved';
        $hrisReq->approved_by = $request->user()->id;
        $hrisReq->approved_at = now();
        $hrisReq->approver_note = $request->note ?? 'Disetujui oleh admin';
        $hrisReq->save();

        // 1. Deduct quota for leave / sick / absence requests
        $this->deductLeaveQuota($hrisReq, $tenantId);

        // 2. Synchronize to attendances calendar & records
        $this->syncApprovedRequestToAttendance($hrisReq, $tenantId);

        return response()->json([
            'message' => 'Permohonan berhasil disetujui.',
            'data' => $hrisReq->load(['employee.user', 'leaveType', 'approver'])
        ]);
    }

    public function reject(Request $request, $id)
    {
        $tenantId = $this->getTenantId($request);
        $hrisReq = HrisRequest::where('tenant_id', $tenantId)->findOrFail($id);

        $wasApproved = $hrisReq->status === 'approved';

        $hrisReq->status = 'rejected';
        $hrisReq->approved_by = $request->user()->id;
        $hrisReq->approved_at = now();
        $hrisReq->approver_note = $request->note ?? 'Ditolak';
        $hrisReq->save();

        // If previously approved, rollback attendance records and restore leave balance quota
        if ($wasApproved) {
            $this->removeRequestFromAttendance($hrisReq);
            $this->restoreLeaveQuota($hrisReq, $tenantId);
        }

        return response()->json([
            'message' => 'Permohonan ditolak.',
            'data' => $hrisReq->load(['employee.user', 'leaveType', 'approver'])
        ]);
    }

    public function destroy(Request $request, $id)
    {
        $tenantId = $this->getTenantId($request);
        $hrisReq = HrisRequest::where('tenant_id', $tenantId)->findOrFail($id);

        if ($hrisReq->status === 'approved') {
            $this->removeRequestFromAttendance($hrisReq);
            $this->restoreLeaveQuota($hrisReq, $tenantId);
        }

        if ($hrisReq->attachment_path) {
            try {
                EncryptedStorageService::deleteFile($hrisReq->attachment_path);
            } catch (\Exception $e) {
                \Log::warning('Failed deleting request attachment file: ' . $e->getMessage());
            }
        }

        $hrisReq->delete();

        return response()->json(['message' => 'Data permohonan berhasil dihapus.']);
    }

    /**
     * Helper: Sync approved leave / permission / sick request to attendances table
     */
    protected function syncApprovedRequestToAttendance(HrisRequest $hrisReq, int $tenantId)
    {
        $start = Carbon::parse($hrisReq->start_date);
        $end = Carbon::parse($hrisReq->end_date);

        $attStatus = 'izin';
        $label = 'Izin Absen';
        if ($hrisReq->request_type === 'izin_sakit') {
            $attStatus = 'sakit';
            $label = 'Izin Sakit';
        } elseif ($hrisReq->request_type === 'izin_cuti') {
            $attStatus = 'cuti';
            $label = $hrisReq->leaveType ? $hrisReq->leaveType->name : 'Cuti';
        } elseif ($hrisReq->request_type === 'izin_dinas') {
            $attStatus = 'dinas';
            $label = 'Dinas Luar';
        } elseif ($hrisReq->request_type === 'izin_absen') {
            $attStatus = 'izin';
            $label = 'Izin Absen';
        }

        for ($date = $start->copy(); $date->lte($end); $date->addDay()) {
            $dateStr = $date->format('Y-m-d');
            Attendance::updateOrCreate(
                [
                    'employee_id' => $hrisReq->employee_id,
                    'date' => $dateStr,
                ],
                [
                    'admin_id' => $tenantId,
                    'status' => $attStatus,
                    'notes' => "{$label}: " . ($hrisReq->reason ?: 'Disetujui oleh admin'),
                    'check_in' => null,
                    'check_out' => null,
                ]
            );
        }
    }

    /**
     * Helper: Remove attendance records associated with a rejected or deleted request
     */
    protected function removeRequestFromAttendance(HrisRequest $hrisReq)
    {
        $start = Carbon::parse($hrisReq->start_date)->format('Y-m-d');
        $end = Carbon::parse($hrisReq->end_date)->format('Y-m-d');

        $attStatus = 'izin';
        if ($hrisReq->request_type === 'izin_sakit') {
            $attStatus = 'sakit';
        } elseif ($hrisReq->request_type === 'izin_cuti') {
            $attStatus = 'cuti';
        } elseif ($hrisReq->request_type === 'izin_dinas') {
            $attStatus = 'dinas';
        } elseif ($hrisReq->request_type === 'izin_absen') {
            $attStatus = 'izin';
        }

        Attendance::where('employee_id', $hrisReq->employee_id)
            ->whereBetween('date', [$start, $end])
            ->where('status', $attStatus)
            ->delete();
    }
/**
     * Helper: Deduct quota from leave / sick / absence balance based on request type
     */
    protected function deductLeaveQuota(HrisRequest $hrisReq, int $tenantId)
    {
        $year = (int)date('Y', strtotime($hrisReq->start_date));
        $reqType = $hrisReq->request_type;

        if ($reqType === 'izin_cuti') {
            $leaveTypeId = $hrisReq->leave_type_id;
            if (!$leaveTypeId) {
                $firstType = HrisLeaveType::where('tenant_id', $tenantId)->first();
                $leaveTypeId = $firstType ? $firstType->id : null;
            }

            if ($leaveTypeId) {
                $typeObj = HrisLeaveType::find($leaveTypeId);
                $defaultDays = $typeObj ? $typeObj->default_days : 12;

                $balance = HrisEmployeeLeaveBalance::firstOrCreate(
                    [
                        'tenant_id' => $tenantId,
                        'employee_id' => $hrisReq->employee_id,
                        'leave_type_id' => $leaveTypeId,
                        'category' => 'leave',
                        'year' => $year,
                    ],
                    [
                        'quota' => $defaultDays,
                        'used' => 0,
                        'remaining' => $defaultDays,
                    ]
                );

                $balance->used = (int)$balance->used + (int)$hrisReq->days_count;
                $balance->remaining = max(0, (int)$balance->quota - (int)$balance->used);
                $balance->save();

                if (!$hrisReq->leave_type_id) {
                    $hrisReq->leave_type_id = $leaveTypeId;
                    $hrisReq->saveQuietly();
                }
            }
        } elseif ($reqType === 'izin_sakit') {
            $policy = HrisRequestPolicy::where('tenant_id', $tenantId)->where('policy_type', 'sick')->first();
            $defaultDays = $policy ? $policy->max_days_per_year : 14;

            $balance = HrisEmployeeLeaveBalance::firstOrCreate(
                [
                    'tenant_id' => $tenantId,
                    'employee_id' => $hrisReq->employee_id,
                    'category' => 'sick',
                    'year' => $year,
                ],
                [
                    'quota' => $defaultDays,
                    'used' => 0,
                    'remaining' => $defaultDays,
                ]
            );

            $balance->used = (int)$balance->used + (int)$hrisReq->days_count;
            $balance->remaining = max(0, (int)$balance->quota - (int)$balance->used);
            $balance->save();
        } elseif ($reqType === 'izin_absen') {
            $policy = HrisRequestPolicy::where('tenant_id', $tenantId)->where('policy_type', 'absence')->first();
            $defaultDays = $policy ? $policy->max_days_per_year : 3;

            $balance = HrisEmployeeLeaveBalance::firstOrCreate(
                [
                    'tenant_id' => $tenantId,
                    'employee_id' => $hrisReq->employee_id,
                    'category' => 'absence',
                    'year' => $year,
                ],
                [
                    'quota' => $defaultDays,
                    'used' => 0,
                    'remaining' => $defaultDays,
                ]
            );

            $balance->used = (int)$balance->used + (int)$hrisReq->days_count;
            $balance->remaining = max(0, (int)$balance->quota - (int)$balance->used);
            $balance->save();
        }
    }

    /**
     * Helper: Restore quota to leave / sick / absence balance when an approved request is rejected or deleted
     */
    protected function restoreLeaveQuota(HrisRequest $hrisReq, int $tenantId)
    {
        $year = (int)date('Y', strtotime($hrisReq->start_date));
        $reqType = $hrisReq->request_type;

        if ($reqType === 'izin_cuti') {
            $leaveTypeId = $hrisReq->leave_type_id;
            if ($leaveTypeId) {
                $balance = HrisEmployeeLeaveBalance::where('tenant_id', $tenantId)
                    ->where('employee_id', $hrisReq->employee_id)
                    ->where('leave_type_id', $leaveTypeId)
                    ->where('category', 'leave')
                    ->where('year', $year)
                    ->first();

                if ($balance) {
                    $balance->used = max(0, (int)$balance->used - (int)$hrisReq->days_count);
                    $balance->remaining = max(0, (int)$balance->quota - (int)$balance->used);
                    $balance->save();
                }
            }
        } elseif ($reqType === 'izin_sakit') {
            $balance = HrisEmployeeLeaveBalance::where('tenant_id', $tenantId)
                ->where('employee_id', $hrisReq->employee_id)
                ->where('category', 'sick')
                ->where('year', $year)
                ->first();

            if ($balance) {
                $balance->used = max(0, (int)$balance->used - (int)$hrisReq->days_count);
                $balance->remaining = max(0, (int)$balance->quota - (int)$balance->used);
                $balance->save();
            }
        } elseif ($reqType === 'izin_absen') {
            $balance = HrisEmployeeLeaveBalance::where('tenant_id', $tenantId)
                ->where('employee_id', $hrisReq->employee_id)
                ->where('category', 'absence')
                ->where('year', $year)
                ->first();

            if ($balance) {
                $balance->used = max(0, (int)$balance->used - (int)$hrisReq->days_count);
                $balance->remaining = max(0, (int)$balance->quota - (int)$balance->used);
                $balance->save();
            }
        }
    }

}
