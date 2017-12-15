<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\HrisContractType;
use Illuminate\Http\Request;

class HrisContractTypeController extends Controller
{
    private function getTenantId(Request $request)
    {
        return $request->user()->isAdmin() ? $request->user()->id : ($request->user()->admin_id ?? $request->user()->id);
    }

    public function index(Request $request)
    {
        $tenantId = $this->getTenantId($request);
        $types = HrisContractType::where('tenant_id', $tenantId)->orderBy('id', 'asc')->get();

        // If empty, auto-seed standard default types
        if ($types->isEmpty()) {
            $defaults = [
                ['code' => 'PKWT', 'name' => 'PKWT (Perjanjian Kerja Waktu Tertentu)', 'is_permanent' => false, 'description' => 'Kontrak kerja dengan jangka waktu tertentu.'],
                ['code' => 'PKWTT', 'name' => 'PKWTT (Karyawan Tetap)', 'is_permanent' => true, 'description' => 'Perjanjian kerja tetap tanpa batas tanggal berakhir.'],
                ['code' => 'FREELANCE', 'name' => 'Freelance / Kemitraan', 'is_permanent' => false, 'description' => 'Perjanjian kerja lepas berbasis proyek / kemitraan.'],
                ['code' => 'MAGANG', 'name' => 'Magang / Internship', 'is_permanent' => false, 'description' => 'Perjanjian program magang atau pelatihan kerja.'],
                ['code' => 'PROBASI', 'name' => 'Masa Percobaan (Probation)', 'is_permanent' => false, 'description' => 'Masa evaluasi kerja 3 bulan sebelum pengangkatan.'],
                ['code' => 'OUTSOURCE', 'name' => 'Alih Daya (Outsourcing)', 'is_permanent' => false, 'description' => 'Tenaga kerja dari pihak ketiga atau mitra vendor.'],
            ];

            foreach ($defaults as $d) {
                HrisContractType::create(array_merge($d, ['tenant_id' => $tenantId, 'is_active' => true]));
            }

            $types = HrisContractType::where('tenant_id', $tenantId)->orderBy('id', 'asc')->get();
        }

        return response()->json([
            'status' => 'success',
            'data' => $types
        ]);
    }

    public function store(Request $request)
    {
        $tenantId = $this->getTenantId($request);

        $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'nullable|string|max:50',
            'is_permanent' => 'boolean',
            'description' => 'nullable|string',
            'is_active' => 'boolean',
        ]);

        $type = HrisContractType::create([
            'tenant_id' => $tenantId,
            'name' => $request->name,
            'code' => $request->code ?: strtoupper(substr($request->name, 0, 8)),
            'is_permanent' => $request->is_permanent ?? false,
            'description' => $request->description,
            'is_active' => $request->is_active ?? true,
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Jenis kontrak berhasil ditambahkan.',
            'data' => $type
        ], 201);
    }

    public function update(Request $request, $id)
    {
        $tenantId = $this->getTenantId($request);
        $type = HrisContractType::where('tenant_id', $tenantId)->findOrFail($id);

        $request->validate([
            'name' => 'sometimes|string|max:255',
            'code' => 'nullable|string|max:50',
            'is_permanent' => 'boolean',
            'description' => 'nullable|string',
            'is_active' => 'boolean',
        ]);

        $type->update($request->only(['name', 'code', 'is_permanent', 'description', 'is_active']));

        return response()->json([
            'status' => 'success',
            'message' => 'Jenis kontrak berhasil diperbarui.',
            'data' => $type
        ]);
    }

    public function destroy(Request $request, $id)
    {
        $tenantId = $this->getTenantId($request);
        $type = HrisContractType::where('tenant_id', $tenantId)->findOrFail($id);
        $type->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Jenis kontrak berhasil dihapus.'
        ]);
    }
}
