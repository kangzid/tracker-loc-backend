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
        return $user->isAdmin() ? $user->id : ($user->admin_id ?? $user->id);
    }

    public function index(Request $request)
    {
        $tenantId = $this->getTenantId($request);
        $query = HrisAsset::where('tenant_id', $tenantId)->with('employee.user');

        if ($request->filled('category')) {
            $query->where('category', $request->category);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('employee_id')) {
            $query->where('employee_id', $request->employee_id);
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
        $query = HrisAsset::where('tenant_id', $tenantId);

        $totalAssets = (clone $query)->count();
        $assignedCount = (clone $query)->where('status', 'assigned')->count();
        $storageCount = (clone $query)->where('status', 'storage')->count();
        $maintenanceCount = (clone $query)->where('status', 'maintenance')->count();
        $disposedCount = (clone $query)->where('status', 'disposed')->count();

        return response()->json([
            'total_assets' => $totalAssets,
            'assigned_count' => $assignedCount,
            'storage_count' => $storageCount,
            'maintenance_count' => $maintenanceCount,
            'disposed_count' => $disposedCount,
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

        $status = $request->filled('employee_id') ? 'assigned' : 'storage';
        $handoverDate = $request->filled('employee_id') ? ($request->handover_date ?? now()->toDateString()) : null;

        $asset = HrisAsset::create([
            'tenant_id' => $tenantId,
            'asset_code' => $code,
            'name' => $request->name,
            'category' => $request->category,
            'serial_number' => $request->serial_number,
            'employee_id' => $request->employee_id,
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
            'notes' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $asset->update($request->only([
            'name', 'category', 'asset_code', 'serial_number', 'condition', 'status', 'notes'
        ]));

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

    public function destroy(Request $request, $id)
    {
        $tenantId = $this->getTenantId($request);
        $asset = HrisAsset::where('tenant_id', $tenantId)->findOrFail($id);
        $asset->delete();

        return response()->json(['message' => 'Aset berhasil dihapus.']);
    }
}
