<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\HrisLeaveType;
use App\Models\HrisEmployeeLeaveBalance;
use App\Models\HrisRequestPolicy;
use App\Models\Employee;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class HrisLeaveSettingController extends Controller
{
    private function getTenantId(Request $request)
    {
        $user = $request->user();
        return $user->role === 'employee' ? ($user->employee ? $user->employee->admin_id : ($user->admin_id ?? $user->id)) : $user->id;
    }

    // ==========================================
    // 1. MASTER JENIS CUTI (MURNI UNTUK IZIN CUTI)
    // ==========================================

    public function getLeaveTypes(Request $request)
    {
        $tenantId = $this->getTenantId($request);
        $types = HrisLeaveType::where('tenant_id', $tenantId)->orderBy('id', 'asc')->get();

        if ($types->isEmpty()) {
            // Seed pure leave types only (No sick / absence codes)
            $defaults = [
                ['code' => 'CT01', 'name' => 'Cuti Tahunan', 'default_days' => 12, 'is_paid' => true, 'requires_attachment' => false, 'description' => 'Hak cuti tahunan reguler karyawan'],
                ['code' => 'CM01', 'name' => 'Cuti Melahirkan', 'default_days' => 90, 'is_paid' => true, 'requires_attachment' => true, 'description' => 'Cuti melahirkan bagi karyawan wanita'],
                ['code' => 'CK01', 'name' => 'Cuti Khusus (Menikah / Duka)', 'default_days' => 3, 'is_paid' => true, 'requires_attachment' => false, 'description' => 'Cuti pernikahan, khitanan, atau keluarga berduka'],
            ];
            foreach ($defaults as $d) {
                HrisLeaveType::create(array_merge($d, ['tenant_id' => $tenantId]));
            }
            $types = HrisLeaveType::where('tenant_id', $tenantId)->orderBy('id', 'asc')->get();
        }

        return response()->json($types);
    }

    public function storeLeaveType(Request $request)
    {
        $tenantId = $this->getTenantId($request);

        $validator = Validator::make($request->all(), [
            'code' => 'required|string|max:50',
            'name' => 'required|string|max:100',
            'default_days' => 'required|integer|min:1',
            'is_paid' => 'boolean',
            'requires_attachment' => 'boolean',
            'description' => 'nullable|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $type = HrisLeaveType::updateOrCreate(
            ['tenant_id' => $tenantId, 'code' => $request->code],
            [
                'name' => $request->name,
                'default_days' => $request->default_days,
                'is_paid' => $request->is_paid ?? true,
                'requires_attachment' => $request->requires_attachment ?? false,
                'description' => $request->description,
            ]
        );

        return response()->json($type, 201);
    }

    public function deleteLeaveType(Request $request, $id)
    {
        $tenantId = $this->getTenantId($request);
        $type = HrisLeaveType::where('tenant_id', $tenantId)->findOrFail($id);
        $type->delete();

        return response()->json(['message' => 'Jenis cuti berhasil dihapus.']);
    }

    // =========================================================================
    // 2. KEBIJAKAN PENGAJUAN INDEPENDEN (IZIN SAKIT, IZIN ABSEN, IZIN DINAS)
    // =========================================================================

    public function getPolicies(Request $request)
    {
        $tenantId = $this->getTenantId($request);
        $policies = HrisRequestPolicy::where('tenant_id', $tenantId)->get()->keyBy('policy_type');

        $defaults = [
            'sick' => [
                'policy_type' => 'sick',
                'max_days_per_year' => 14,
                'requires_attachment' => true,
                'is_paid' => true,
                'description' => 'Izin sakit dengan Surat Izin Dokter (SID) resmi'
            ],
            'absence' => [
                'policy_type' => 'absence',
                'max_days_per_year' => 3,
                'requires_attachment' => false,
                'is_paid' => false,
                'description' => 'Izin keperluan pribadi mendesak / absen harian'
            ],
            'duty' => [
                'policy_type' => 'duty',
                'max_days_per_year' => 0,
                'requires_attachment' => true,
                'is_paid' => true,
                'description' => 'Perjalanan dinas luar kota / penugasan kantor'
            ],
        ];

        $result = [];
        foreach ($defaults as $type => $def) {
            if (isset($policies[$type])) {
                $result[$type] = $policies[$type];
            } else {
                $created = HrisRequestPolicy::create(array_merge($def, ['tenant_id' => $tenantId]));
                $result[$type] = $created;
            }
        }

        return response()->json($result);
    }

    public function savePolicy(Request $request)
    {
        $tenantId = $this->getTenantId($request);

        $validator = Validator::make($request->all(), [
            'policy_type' => 'required|in:sick,absence,duty',
            'max_days_per_year' => 'nullable|integer|min:0',
            'requires_attachment' => 'boolean',
            'is_paid' => 'boolean',
            'description' => 'nullable|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $policy = HrisRequestPolicy::updateOrCreate(
            ['tenant_id' => $tenantId, 'policy_type' => $request->policy_type],
            [
                'max_days_per_year' => $request->max_days_per_year ?? 0,
                'requires_attachment' => $request->requires_attachment ?? false,
                'is_paid' => $request->is_paid ?? true,
                'description' => $request->description,
            ]
        );

        return response()->json($policy);
    }

    // =========================================================================
    // 3. SALDO & KUOTA KARYAWAN (BERDASARKAN KATEGORI: LEAVE / SICK / ABSENCE)
    // =========================================================================

    public function getLeaveBalances(Request $request)
    {
        $tenantId = $this->getTenantId($request);
        $year = (int)($request->year ?? date('Y'));
        $category = $request->category ?: 'leave'; // 'leave', 'sick', 'absence'

        $employees = Employee::where('admin_id', $tenantId)->where('is_active', true)->get();

        if ($category === 'leave') {
            // Check pure leave types
            $leaveTypes = HrisLeaveType::where('tenant_id', $tenantId)->get();
            if ($leaveTypes->isEmpty()) {
                $this->getLeaveTypes($request);
                $leaveTypes = HrisLeaveType::where('tenant_id', $tenantId)->get();
            }

            foreach ($employees as $emp) {
                foreach ($leaveTypes as $lt) {
                    HrisEmployeeLeaveBalance::firstOrCreate(
                        [
                            'tenant_id' => $tenantId,
                            'employee_id' => $emp->id,
                            'leave_type_id' => $lt->id,
                            'category' => 'leave',
                            'year' => $year,
                        ],
                        [
                            'quota' => $lt->default_days ?? 12,
                            'used' => 0,
                            'remaining' => $lt->default_days ?? 12,
                        ]
                    );
                }
            }

            $balances = HrisEmployeeLeaveBalance::where('tenant_id', $tenantId)
                ->where('year', $year)
                ->where('category', 'leave')
                ->with(['employee.user', 'leaveType'])
                ->orderBy('employee_id', 'asc')
                ->orderBy('leave_type_id', 'asc')
                ->get();

            return response()->json($balances);
        }

        // For category 'sick' or 'absence'
        $policy = HrisRequestPolicy::where('tenant_id', $tenantId)->where('policy_type', $category)->first();
        $defaultQuota = $policy ? $policy->max_days_per_year : ($category === 'sick' ? 14 : 3);

        foreach ($employees as $emp) {
            HrisEmployeeLeaveBalance::firstOrCreate(
                [
                    'tenant_id' => $tenantId,
                    'employee_id' => $emp->id,
                    'category' => $category,
                    'year' => $year,
                ],
                [
                    'quota' => $defaultQuota,
                    'used' => 0,
                    'remaining' => $defaultQuota,
                ]
            );
        }

        $balances = HrisEmployeeLeaveBalance::where('tenant_id', $tenantId)
            ->where('year', $year)
            ->where('category', $category)
            ->with(['employee.user'])
            ->orderBy('employee_id', 'asc')
            ->get();

        return response()->json($balances);
    }

    public function updateLeaveBalance(Request $request, $id)
    {
        $tenantId = $this->getTenantId($request);
        $balance = HrisEmployeeLeaveBalance::where('tenant_id', $tenantId)->findOrFail($id);

        $validator = Validator::make($request->all(), [
            'quota' => 'required|integer|min:0',
            'used' => 'required|integer|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $quota = (int)$request->quota;
        $used = (int)$request->used;
        $remaining = max(0, $quota - $used);

        $balance->update([
            'quota' => $quota,
            'used' => $used,
            'remaining' => $remaining,
        ]);

        return response()->json($balance->fresh(['employee.user', 'leaveType']));
    }
}
