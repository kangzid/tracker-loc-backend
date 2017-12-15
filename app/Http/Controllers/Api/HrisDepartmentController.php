<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\HrisDepartment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class HrisDepartmentController extends Controller
{
    private function getTenantId(Request $request)
    {
        $user = $request->user();
        return $user->role === 'superadmin' ? ($user->tenant_id ?? 1) : ($user->admin_id ?? $user->id);
    }

    public function index(Request $request)
    {
        $tenantId = $this->getTenantId($request);
        $query = HrisDepartment::with(['positions', 'manager.user'])
            ->withCount(['positions'])
            ->where('tenant_id', $tenantId);

        if ($request->has('search') && $request->search) {
            $s = $request->search;
            $query->where(function($q) use ($s) {
                $q->where('name', 'like', "%{$s}%")
                  ->orWhere('code', 'like', "%{$s}%")
                  ->orWhere('description', 'like', "%{$s}%");
            });
        }

        if ($request->has('is_active')) {
            $query->where('is_active', filter_var($request->is_active, FILTER_VALIDATE_BOOLEAN));
        }

        $departments = $query->orderBy('name', 'asc')->get();
        return response()->json($departments);
    }

    public function store(Request $request)
    {
        $tenantId = $this->getTenantId($request);

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:100',
            'code' => 'nullable|string|max:50',
            'description' => 'nullable|string',
            'manager_id' => 'nullable|exists:employees,id',
            'is_active' => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $dept = HrisDepartment::create([
            'tenant_id' => $tenantId,
            'name' => $request->name,
            'code' => $request->code ? strtoupper($request->code) : null,
            'description' => $request->description,
            'manager_id' => $request->manager_id,
            'is_active' => $request->has('is_active') ? $request->is_active : true,
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Departemen berhasil ditambahkan.',
            'data' => $dept->load(['positions', 'manager.user'])
        ], 201);
    }

    public function show(Request $request, $id)
    {
        $tenantId = $this->getTenantId($request);
        $dept = HrisDepartment::with(['positions', 'manager.user'])
            ->where('tenant_id', $tenantId)
            ->findOrFail($id);

        return response()->json($dept);
    }

    public function update(Request $request, $id)
    {
        $tenantId = $this->getTenantId($request);
        $dept = HrisDepartment::where('tenant_id', $tenantId)->findOrFail($id);

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|required|string|max:100',
            'code' => 'nullable|string|max:50',
            'description' => 'nullable|string',
            'manager_id' => 'nullable|exists:employees,id',
            'is_active' => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $oldName = $dept->name;
        $dept->update([
            'name' => $request->has('name') ? $request->name : $dept->name,
            'code' => $request->has('code') ? ($request->code ? strtoupper($request->code) : null) : $dept->code,
            'description' => $request->has('description') ? $request->description : $dept->description,
            'manager_id' => $request->has('manager_id') ? $request->manager_id : $dept->manager_id,
            'is_active' => $request->has('is_active') ? $request->is_active : $dept->is_active,
        ]);

        // Optional: If name changed, update employees/contracts referring to old department name
        if ($request->has('name') && $request->name !== $oldName) {
            \App\Models\Employee::where('tenant_id', $tenantId)->where('department', $oldName)->update(['department' => $request->name]);
            \App\Models\HrisContract::where('tenant_id', $tenantId)->where('department', $oldName)->update(['department' => $request->name]);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Data departemen berhasil diperbarui.',
            'data' => $dept->load(['positions', 'manager.user'])
        ]);
    }

    public function destroy(Request $request, $id)
    {
        $tenantId = $this->getTenantId($request);
        $dept = HrisDepartment::where('tenant_id', $tenantId)->findOrFail($id);
        
        // Cascade or remove positions
        $dept->positions()->delete();
        $dept->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Departemen dan posisi terkait berhasil dihapus.'
        ]);
    }
}
