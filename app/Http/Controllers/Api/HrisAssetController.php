<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\HrisAsset;
use App\Models\Employee;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class HrisAssetController extends Controller
{
    private function getTenantId(Request $request)
    {
        $user = $request->user();
        if ($user->role === 'employee') {
            return $user->employee->admin_id ?? $user->admin_id ?? 2;
        }
        return $user->isAdmin() ? $user->id : ($user->admin_id ?? $user->id ?? 2);
    }

    public function index(Request $request)
    {
        $tenantId = $this->getTenantId($request);
        $user = $request->user();
        $query = HrisAsset::where('tenant_id', $tenantId)->with('employee.user');

        if ($request->filled('category') && $request->category !== 'all') {
            $query->where('category', $request->category);
        }

        if ($request->filled('status') && $request->status !== 'all') {
            $query->where('status', $request->status);
        }

        // Employee filter: if employee_id specified or logged in as employee
        if ($request->filled('employee_id')) {
            $query->where('employee_id', $request->employee_id);
        } elseif ($user && $user->role === 'employee' && !$request->boolean('all')) {
            $empId = $user->employee ? $user->employee->id : null;
            if ($empId) {
                $query->where('employee_id', $empId);
            }
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('asset_code', 'like', "%{$search}%")
                  ->orWhere('name', 'like', "%{$search}%")
                  ->orWhere('serial_number', 'like', "%{$search}%")
                  ->orWhereHas('employee', function ($eq) use ($search) {
                      $eq->where('employee_id', 'like', "%{$search}%")
                         ->orWhereHas('user', function ($uq) use ($search) {
                             $uq->where('name', 'like', "%{$search}%");
                         });
                  });
            });
        }

        $items = $query->orderBy('id', 'desc')->get();
        return response()->json($items);
    }

    public function summary(Request $request)
    {
        $tenantId = $this->getTenantId($request);
        $user = $request->user();
        $employeeId = $request->query('employee_id') ?? ($user && $user->role === 'employee' ? ($user->employee ? $user->employee->id : null) : null);

        $query = HrisAsset::where('tenant_id', $tenantId);
        if ($employeeId) {
            $query->where('employee_id', $employeeId);
        }

        $totalAssets = (clone $query)->count();
        $assignedCount = (clone $query)->where('status', 'assigned')->count();
        $storageCount = (clone $query)->where('status', 'storage')->count();
        
        // Maintenance count: items with status = 'maintenance' OR condition not in ['Baik', 'Baru'] (perlu servis / rusak)
        $maintenanceCount = (clone $query)->where(function ($q) {
            $q->where('status', 'maintenance')
              ->orWhere(function ($sub) {
                  $sub->whereNotIn('condition', ['Baik', 'Baru', 'good', 'new'])
                      ->whereNotNull('condition')
                      ->where('condition', '!=', '');
              });
        })->count();

        $disposedCount = (clone $query)->where('status', 'disposed')->count();
        $goodConditionCount = (clone $query)->where(function($q) {
            $q->whereIn('condition', ['Baik', 'Baru', 'good', 'new'])
              ->orWhereNull('condition');
        })->count();

        return response()->json([
            'total_assets' => $totalAssets,
            'assigned_count' => $assignedCount,
            'storage_count' => $storageCount,
            'maintenance_count' => $maintenanceCount,
            'disposed_count' => $disposedCount,
            'good_condition_count' => $goodConditionCount,
        ]);
    }

    public function store(Request $request)
    {
        $tenantId = $this->getTenantId($request);

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:150',
            'category' => 'required|string|max:50',
            'asset_code' => 'nullable|string|max:50',
            'serial_number' => 'nullable|string|max:100',
            'condition' => 'nullable|string|max:50',
            'notes' => 'nullable|string',
            'employee_id' => 'nullable|exists:employees,id',
            'handover_date' => 'nullable|date',
            'status' => 'nullable|in:storage,assigned,maintenance,disposed',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // Auto-generate asset code if not provided: AST-[0001]
        $code = $request->asset_code;
        if (empty($code)) {
            $last = HrisAsset::where('tenant_id', $tenantId)->orderBy('id', 'desc')->first();
            $next = $last ? ($last->id + 1) : 1;
            $code = 'AST-' . str_pad($next, 4, '0', STR_PAD_LEFT);
        }

        $status = $request->status ?: ($request->filled('employee_id') ? 'assigned' : 'storage');
        $handoverDate = ($status === 'assigned' && $request->filled('employee_id')) ? ($request->handover_date ?? now()->toDateString()) : null;

        $asset = HrisAsset::create([
            'tenant_id' => $tenantId,
            'asset_code' => $code,
            'name' => $request->name,
            'category' => $request->category,
            'serial_number' => $request->serial_number,
            'employee_id' => $status === 'assigned' ? $request->employee_id : null,
            'status' => $status,
            'handover_date' => $handoverDate,
            'condition' => $request->condition ?? 'Baik',
            'notes' => $request->notes,
        ]);

        return response()->json($asset->load('employee.user'), 201);
    }

    public function update(Request $request, $id)
    {
        $tenantId = $this->getTenantId($request);
        $asset = HrisAsset::where('tenant_id', $tenantId)->findOrFail($id);

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:150',
            'category' => 'required|string|max:50',
            'asset_code' => 'required|string|max:50',
            'serial_number' => 'nullable|string|max:100',
            'condition' => 'nullable|string|max:50',
            'status' => 'required|in:storage,assigned,maintenance,disposed',
            'employee_id' => 'nullable|exists:employees,id',
            'handover_date' => 'nullable|date',
            'notes' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $data = $request->only([
            'name', 'category', 'asset_code', 'serial_number', 'condition', 'status', 'notes'
        ]);

        if ($request->status === 'storage' || $request->status === 'maintenance' || $request->status === 'disposed') {
            if ($request->status !== 'assigned') {
                $data['employee_id'] = null;
            }
        } elseif ($request->filled('employee_id')) {
            $data['employee_id'] = $request->employee_id;
            if ($request->filled('handover_date')) {
                $data['handover_date'] = $request->handover_date;
            }
        }

        $asset->update($data);

        return response()->json($asset->load('employee.user'));
    }

    public function assign(Request $request, $id)
    {
        $tenantId = $this->getTenantId($request);
        $asset = HrisAsset::where('tenant_id', $tenantId)->findOrFail($id);

        $request->validate([
            'employee_id' => 'required|exists:employees,id',
            'handover_date' => 'required|date',
            'condition' => 'nullable|string|max:50',
            'notes' => 'nullable|string',
        ]);

        $asset->update([
            'employee_id' => $request->employee_id,
            'status' => 'assigned',
            'handover_date' => $request->handover_date,
            'return_date' => null,
            'condition' => $request->condition ?? $asset->condition,
            'notes' => $request->notes ?? $asset->notes,
        ]);

        return response()->json([
            'message' => 'Aset berhasil diserah-terimakan ke karyawan.',
            'data' => $asset->load('employee.user')
        ]);
    }

    public function returnAsset(Request $request, $id)
    {
        $tenantId = $this->getTenantId($request);
        $asset = HrisAsset::where('tenant_id', $tenantId)->findOrFail($id);

        $request->validate([
            'return_date' => 'required|date',
            'condition' => 'nullable|string|max:50',
            'notes' => 'nullable|string',
        ]);

        $asset->update([
            'employee_id' => null,
            'status' => 'storage',
            'return_date' => $request->return_date,
            'condition' => $request->condition ?? $asset->condition,
            'notes' => $request->notes ?? $asset->notes,
        ]);

        return response()->json([
            'message' => 'Aset berhasil dikembalikan ke gudang penyimpanan.',
            'data' => $asset
        ]);
    }

    public function reportDamage(Request $request, $id)
    {
        $tenantId = $this->getTenantId($request);
        $asset = HrisAsset::where('tenant_id', $tenantId)->findOrFail($id);

        $request->validate([
            'issue_description' => 'required|string|max:500',
            'condition' => 'nullable|string|max:50',
        ]);

        $prevNotes = $asset->notes ? $asset->notes . "\n" : "";
        $newNotes = $prevNotes . "[Laporan Kendala " . now()->format('d/m/Y H:i') . "]: " . $request->issue_description;

        $asset->update([
            'condition' => $request->condition ?? 'Perlu Servis',
            'notes' => $newNotes,
        ]);

        return response()->json([
            'message' => 'Laporan kendala aset berhasil dikirim ke Admin / IT Support.',
            'data' => $asset->load('employee.user')
        ]);
    }

    public function updateMaintenanceStatus(Request $request, $id)
    {
        $tenantId = $this->getTenantId($request);
        $asset = HrisAsset::where('tenant_id', $tenantId)->findOrFail($id);

        $request->validate([
            'action' => 'required|in:send_to_service,service_completed',
            'notes' => 'nullable|string'
        ]);

        if ($request->action === 'send_to_service') {
            $prevNotes = $asset->notes ? $asset->notes . "\n" : "";
            $asset->update([
                'status' => 'maintenance',
                'condition' => 'Perlu Servis',
                'notes' => $prevNotes . ($request->notes ? "[Masuk Servis]: " . $request->notes : "[Status]: Dikirim ke perbaikan / servis")
            ]);
            $msg = 'Aset berhasil dipindahkan ke status Dalam Perbaikan (Maintenance).';
        } else {
            $prevNotes = $asset->notes ? $asset->notes . "\n" : "";
            $asset->update([
                'status' => 'storage',
                'condition' => 'Baik',
                'employee_id' => null,
                'notes' => $prevNotes . ($request->notes ? "[Selesai Servis]: " . $request->notes : "[Status]: Selesai diservis dan kembali ke gudang dalam kondisi Baik")
            ]);
            $msg = 'Aset telah selesai diperbaiki dan siap di gudang penyimpanan.';
        }

        return response()->json([
            'message' => $msg,
            'data' => $asset->load('employee.user')
        ]);
    }

    public function destroy(Request $request, $id)
    {
        $tenantId = $this->getTenantId($request);
        $asset = HrisAsset::where('tenant_id', $tenantId)->findOrFail($id);
        $asset->delete();

        return response()->json(['message' => 'Aset berhasil dihapus.']);
    }
}
