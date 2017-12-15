<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\HrisPosition;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class HrisPositionController extends Controller
{
    private function getTenantId(Request $request)
    {
        $user = $request->user();
        return $user->role === 'superadmin' ? ($user->tenant_id ?? 1) : ($user->admin_id ?? $user->id);
    }

    public function index(Request $request)
    {
        $tenantId = $this->getTenantId($request);
        $query = HrisPosition::with('department')
            ->where('tenant_id', $tenantId);

        if ($request->has('department_id') && $request->department_id) {
            $query->where('department_id', $request->department_id);
        }

        if ($request->has('search') && $request->search) {
            $s = $request->search;
            $query->where(function($q) use ($s) {
                $q->where('name', 'like', "%{$s}%")
                  ->orWhere('code', 'like', "%{$s}%")
                  ->orWhere('level', 'like', "%{$s}%")
                  ->orWhere('description', 'like', "%{$s}%");
            });
        }

        if ($request->has('is_active')) {
            $query->where('is_active', filter_var($request->is_active, FILTER_VALIDATE_BOOLEAN));
        }

        $positions = $query->orderBy('name', 'asc')->get();
        return response()->json($positions);
    }

    public function store(Request $request)
    {
        $tenantId = $this->getTenantId($request);

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:100',
            'department_id' => 'nullable|exists:hris_departments,id',
            'code' => 'nullable|string|max:50',
            'level' => 'nullable|string|max:50',
            'description' => 'nullable|string',
            'is_active' => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $pos = HrisPosition::create([
            'tenant_id' => $tenantId,
            'department_id' => $request->department_id,
            'name' => $request->name,
            'code' => $request->code ? strtoupper($request->code) : null,
            'level' => $request->level ?: 'Staff',
            'description' => $request->description,
            'is_active' => $request->has('is_active') ? $request->is_active : true,
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Posisi / Jabatan berhasil ditambahkan.',
            'data' => $pos->load('department')
        ], 201);
    }

    public function show(Request $request, $id)
    {
        $tenantId = $this->getTenantId($request);
        $pos = HrisPosition::with('department')
            ->where('tenant_id', $tenantId)
            ->findOrFail($id);

        return response()->json($pos);
    }

    public function update(Request $request, $id)
    {
        $tenantId = $this->getTenantId($request);
        $pos = HrisPosition::where('tenant_id', $tenantId)->findOrFail($id);

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|required|string|max:100',
            'department_id' => 'nullable|exists:hris_departments,id',
            'code' => 'nullable|string|max:50',
            'level' => 'nullable|string|max:50',
            'description' => 'nullable|string',
            'is_active' => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $oldName = $pos->name;
        $pos->update([
            'name' => $request->has('name') ? $request->name : $pos->name,
            'department_id' => $request->has('department_id') ? $request->department_id : $pos->department_id,
            'code' => $request->has('code') ? ($request->code ? strtoupper($request->code) : null) : $pos->code,
            'level' => $request->has('level') ? $request->level : $pos->level,
            'description' => $request->has('description') ? $request->description : $pos->description,
            'is_active' => $request->has('is_active') ? $request->is_active : $pos->is_active,
        ]);

        // If name changed, optionally sync employees/contracts
        if ($request->has('name') && $request->name !== $oldName) {
            \App\Models\Employee::where('tenant_id', $tenantId)->where('position', $oldName)->update(['position' => $request->name]);
            \App\Models\HrisContract::where('tenant_id', $tenantId)->where('position', $oldName)->update(['position' => $request->name]);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Posisi / Jabatan berhasil diperbarui.',
            'data' => $pos->load('department')
        ]);
    }

    public function destroy(Request $request, $id)
    {
        $tenantId = $this->getTenantId($request);
        $pos = HrisPosition::where('tenant_id', $tenantId)->findOrFail($id);
        $pos->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Posisi / Jabatan berhasil dihapus.'
        ]);
    }
}
