<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\VehicleType;
use App\Models\Vehicle;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class VehicleTypeController extends Controller
{
    private function getTenantId(Request $request)
    {
        $user = $request->user();
        return $user->role === 'superadmin' ? ($user->tenant_id ?? 1) : ($user->admin_id ?? $user->id);
    }

    public function index(Request $request)
    {
        $tenantId = $this->getTenantId($request);
        $query = VehicleType::where('tenant_id', $tenantId);

        if ($request->has('search') && $request->search) {
            $s = $request->search;
            $query->where(function($q) use ($s) {
                $q->where('name', 'like', "%{$s}%")
                  ->orWhere('code', 'like', "%{$s}%")
                  ->orWhere('category', 'like', "%{$s}%")
                  ->orWhere('description', 'like', "%{$s}%");
            });
        }

        if ($request->has('category') && $request->category !== 'all') {
            $query->where('category', $request->category);
        }

        if ($request->has('is_active')) {
            $query->where('is_active', filter_var($request->is_active, FILTER_VALIDATE_BOOLEAN));
        }

        $types = $query->orderBy('name', 'asc')->get();
        return response()->json($types);
    }

    public function store(Request $request)
    {
        $tenantId = $this->getTenantId($request);

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:100',
            'code' => 'nullable|string|max:50',
            'category' => 'nullable|string|max:50',
            'description' => 'nullable|string',
            'is_active' => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $type = VehicleType::create([
            'tenant_id' => $tenantId,
            'name' => $request->name,
            'code' => $request->code ? strtoupper($request->code) : null,
            'category' => $request->category ?: 'Mobil',
            'description' => $request->description,
            'is_active' => $request->has('is_active') ? $request->is_active : true,
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Tipe kendaraan berhasil ditambahkan.',
            'data' => $type
        ], 201);
    }

    public function show(Request $request, $id)
    {
        $tenantId = $this->getTenantId($request);
        $type = VehicleType::where('tenant_id', $tenantId)->findOrFail($id);

        return response()->json($type);
    }

    public function update(Request $request, $id)
    {
        $tenantId = $this->getTenantId($request);
        $type = VehicleType::where('tenant_id', $tenantId)->findOrFail($id);

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|required|string|max:100',
            'code' => 'nullable|string|max:50',
            'category' => 'nullable|string|max:50',
            'description' => 'nullable|string',
            'is_active' => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $oldName = $type->name;
        $type->update([
            'name' => $request->has('name') ? $request->name : $type->name,
            'code' => $request->has('code') ? ($request->code ? strtoupper($request->code) : null) : $type->code,
            'category' => $request->has('category') ? $request->category : $type->category,
            'description' => $request->has('description') ? $request->description : $type->description,
            'is_active' => $request->has('is_active') ? $request->is_active : $type->is_active,
        ]);

        // If name changed, update existing vehicles with old name
        if ($request->has('name') && $request->name !== $oldName) {
            Vehicle::where('admin_id', $tenantId)->where('vehicle_type', $oldName)->update(['vehicle_type' => $request->name]);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Tipe kendaraan berhasil diperbarui.',
            'data' => $type
        ]);
    }

    public function destroy(Request $request, $id)
    {
        $tenantId = $this->getTenantId($request);
        $type = VehicleType::where('tenant_id', $tenantId)->findOrFail($id);
        $type->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Tipe kendaraan berhasil dihapus.'
        ]);
    }
}
