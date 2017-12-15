<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\HrisMutation;
use App\Models\Employee;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class HrisMutationController extends Controller
{
    private function getTenantId(Request $request)
    {
        return $request->user()->isAdmin() ? $request->user()->id : ($request->user()->admin_id ?? $request->user()->id);
    }

    public function index(Request $request)
    {
        $tenantId = $this->getTenantId($request);
        $query = HrisMutation::with(['employee.user', 'creator'])
            ->where('tenant_id', $tenantId);

        if ($request->filled('employee_id')) {
            $query->where('employee_id', $request->employee_id);
        }

        if ($request->filled('mutation_type')) {
            $query->where('mutation_type', $request->mutation_type);
        }

        if ($request->filled('search')) {
            $s = $request->search;
            $query->where(function($q) use ($s) {
                $q->where('mutation_number', 'like', "%{$s}%")
                  ->orWhere('new_department', 'like', "%{$s}%")
                  ->orWhere('new_position', 'like', "%{$s}%")
                  ->orWhere('reason', 'like', "%{$s}%")
                  ->orWhereHas('employee.user', function($uq) use ($s) {
                      $uq->where('name', 'like', "%{$s}%");
                  });
            });
        }

        $mutations = $query->orderBy('effective_date', 'desc')->orderBy('id', 'desc')->get();

        return response()->json([
            'status' => 'success',
            'data' => $mutations
        ]);
    }

    public function summary(Request $request)
    {
        $tenantId = $this->getTenantId($request);
        $total = HrisMutation::where('tenant_id', $tenantId)->count();
        $promotions = HrisMutation::where('tenant_id', $tenantId)->where('mutation_type', 'like', '%Promosi%')->count();
        $rotations = HrisMutation::where('tenant_id', $tenantId)->where('mutation_type', 'like', '%Rotasi%')->orWhere('mutation_type', 'like', '%Departemen%')->count();
        $demotions = HrisMutation::where('tenant_id', $tenantId)->where('mutation_type', 'like', '%Demosi%')->count();

        return response()->json([
            'status' => 'success',
            'data' => [
                'total_mutations' => $total,
                'promotions_count' => $promotions,
                'rotations_count' => $rotations,
                'demotions_count' => $demotions,
            ]
        ]);
    }

    public function store(Request $request)
    {
        $tenantId = $this->getTenantId($request);

        $request->validate([
            'employee_id' => 'required|exists:employees,id',
            'mutation_type' => 'required|string',
            'effective_date' => 'required|date',
            'new_department' => 'nullable|string',
            'new_position' => 'nullable|string',
            'new_employment_status' => 'nullable|string',
            'reason' => 'nullable|string',
            'document_sk_base64' => 'nullable|string',
            'document_sk_name' => 'nullable|string',
        ]);

        $emp = Employee::findOrFail($request->employee_id);

        $count = HrisMutation::where('tenant_id', $tenantId)->count() + 1;
        $mutationNumber = 'SK-MUT/' . date('Ym') . '/' . str_pad($count, 4, '0', STR_PAD_LEFT);

        $filePath = null;
        $fileName = $request->document_sk_name;

        if ($request->filled('document_sk_base64')) {
            $rawBase64 = preg_replace('#^data:[^;]+;base64,#i', '', $request->document_sk_base64);
            $ext = 'pdf';
            if (strpos($request->document_sk_base64, 'image/png') !== false) $ext = 'png';
            elseif (strpos($request->document_sk_base64, 'image/jpeg') !== false) $ext = 'jpg';

            $fileName = 'SK_' . time() . '_' . Str::random(6) . '.' . $ext;
            $tenantFolder = "tenants/{$tenantId}/mutations";
            $filePath = "{$tenantFolder}/{$fileName}";
            
            Storage::disk('public')->put($filePath, base64_decode($rawBase64));
        }

        $mutation = HrisMutation::create([
            'tenant_id' => $tenantId,
            'employee_id' => $request->employee_id,
            'mutation_number' => $mutationNumber,
            'mutation_type' => $request->mutation_type,
            'effective_date' => $request->effective_date,
            'old_department' => $emp->department,
            'new_department' => $request->new_department ?: $emp->department,
            'old_position' => $emp->position,
            'new_position' => $request->new_position ?: $emp->position,
            'old_employment_status' => $emp->employment_status ?? 'PKWT',
            'new_employment_status' => $request->new_employment_status ?: ($emp->employment_status ?? 'PKWT'),
            'reason' => $request->reason,
            'document_sk_path' => $filePath,
            'document_sk_name' => $fileName,
            'document_sk_base64' => $request->document_sk_base64,
            'status' => 'approved',
            'created_by' => $request->user()->id,
        ]);

        // Automatically update current employee record with new department & position!
        if ($request->filled('new_department')) {
            $emp->department = $request->new_department;
        }
        if ($request->filled('new_position')) {
            $emp->position = $request->new_position;
        }
        $emp->save();

        return response()->json([
            'status' => 'success',
            'message' => 'Data mutasi & promosi berhasil diterbitkan dan data posisi karyawan telah disinkronkan.',
            'data' => $mutation->load('employee.user')
        ], 201);
    }

    public function show(Request $request, $id)
    {
        $tenantId = $this->getTenantId($request);
        $mutation = HrisMutation::with(['employee.user', 'creator'])
            ->where('tenant_id', $tenantId)
            ->findOrFail($id);

        return response()->json(['status' => 'success', 'data' => $mutation]);
    }

    public function update(Request $request, $id)
    {
        $tenantId = $this->getTenantId($request);
        $mutation = HrisMutation::where('tenant_id', $tenantId)->findOrFail($id);

        $mutation->update($request->only([
            'mutation_type', 'effective_date', 'new_department',
            'new_position', 'new_employment_status', 'reason', 'status'
        ]));

        return response()->json([
            'status' => 'success',
            'message' => 'Data mutasi berhasil diperbarui.',
            'data' => $mutation->load('employee.user')
        ]);
    }

    public function destroy(Request $request, $id)
    {
        $tenantId = $this->getTenantId($request);
        $mutation = HrisMutation::where('tenant_id', $tenantId)->findOrFail($id);

        if ($mutation->document_sk_path && Storage::disk('public')->exists($mutation->document_sk_path)) {
            Storage::disk('public')->delete($mutation->document_sk_path);
        }

        $mutation->delete();

        return response()->json(['status' => 'success', 'message' => 'Data mutasi berhasil dihapus.']);
    }
}
