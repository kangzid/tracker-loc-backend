<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\HrisViolation;
use App\Models\HrisViolationType;
use App\Models\Employee;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class HrisViolationController extends Controller
{
    private function getTenantId(Request $request)
    {
        $user = $request->user();
        return $user->isAdmin() ? $user->id : ($user->admin_id ?? $user->id);
    }

    public function types(Request $request)
    {
        $tenantId = $this->getTenantId($request);
        $types = HrisViolationType::where('tenant_id', $tenantId)->orderBy('id', 'asc')->get();

        // Seed default if empty
        if ($types->isEmpty()) {
            $defaults = [
                ['code' => 'SP1', 'name' => 'Surat Peringatan I (SP 1)', 'default_duration_months' => 6, 'description' => 'Peringatan pertama atas pelanggaran tata tertib / SOP kerja'],
                ['code' => 'SP2', 'name' => 'Surat Peringatan II (SP 2)', 'default_duration_months' => 6, 'description' => 'Peringatan kedua atas pengulangan pelanggaran atau kelalaian serius'],
                ['code' => 'SP3', 'name' => 'Surat Peringatan III / Terakhir (SP 3)', 'default_duration_months' => 6, 'description' => 'Peringatan terakhir sebelum pemutusan hubungan kerja (PHK)'],
                ['code' => 'TEGURAN_LISAN', 'name' => 'Teguran Lisan', 'default_duration_months' => 1, 'description' => 'Teguran pendisiplinan lisan yang dicatat dalam berkas HR'],
                ['code' => 'TEGURAN_TERTULIS', 'name' => 'Surat Teguran Tertulis', 'default_duration_months' => 3, 'description' => 'Teguran tertulis resmi sebelum peningkatan sanksi SP'],
            ];

            foreach ($defaults as $d) {
                HrisViolationType::create(array_merge($d, ['tenant_id' => $tenantId, 'is_active' => true]));
            }

            $types = HrisViolationType::where('tenant_id', $tenantId)->orderBy('id', 'asc')->get();
        }

        return response()->json($types);
    }

    public function storeType(Request $request)
    {
        $tenantId = $this->getTenantId($request);

        $validator = Validator::make($request->all(), [
            'code' => 'required|string|max:50',
            'name' => 'required|string|max:100',
            'default_duration_months' => 'required|integer|min:1|max:60',
            'description' => 'nullable|string',
            'is_active' => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $type = HrisViolationType::create([
            'tenant_id' => $tenantId,
            'code' => strtoupper($request->code),
            'name' => $request->name,
            'default_duration_months' => $request->default_duration_months,
            'description' => $request->description,
            'is_active' => $request->is_active ?? true,
        ]);

        return response()->json($type, 201);
    }

    public function updateType(Request $request, $id)
    {
        $tenantId = $this->getTenantId($request);
        $type = HrisViolationType::where('tenant_id', $tenantId)->findOrFail($id);

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:100',
            'default_duration_months' => 'required|integer|min:1|max:60',
            'description' => 'nullable|string',
            'is_active' => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $type->update($request->all());
        return response()->json($type);
    }

    public function destroyType(Request $request, $id)
    {
        $tenantId = $this->getTenantId($request);
        $type = HrisViolationType::where('tenant_id', $tenantId)->findOrFail($id);
        $type->delete();

        return response()->json(['message' => 'Jenis pelanggaran berhasil dihapus.']);
    }

    public function index(Request $request)
    {
        $tenantId = $this->getTenantId($request);
        $query = HrisViolation::where('tenant_id', $tenantId)
            ->with(['employee.user', 'issuer']);

        if ($request->filled('employee_id')) {
            $query->where('employee_id', $request->employee_id);
        }

        if ($request->filled('violation_type')) {
            $query->where('violation_type', $request->violation_type);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('document_number', 'like', "%{$search}%")
                  ->orWhere('contract_number', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%")
                  ->orWhere('violation_type', 'like', "%{$search}%")
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
        $query = HrisViolation::where('tenant_id', $tenantId);

        $total = (clone $query)->count();
        $active = (clone $query)->where('status', 'active')->where('valid_until', '>=', now()->toDateString())->count();
        $expired = (clone $query)->where('status', 'expired')->orWhere('valid_until', '<', now()->toDateString())->count();
        $spCount = (clone $query)->where('violation_type', 'like', '%SP%')->count();
        $teguranCount = (clone $query)->where('violation_type', 'like', '%Teguran%')->count();

        return response()->json([
            'total_violations' => $total,
            'active_count' => $active,
            'expired_count' => $expired,
            'sp_count' => $spCount,
            'teguran_count' => $teguranCount,
        ]);
    }

    public function store(Request $request)
    {
        $tenantId = $this->getTenantId($request);

        $validator = Validator::make($request->all(), [
            'employee_id' => 'required|exists:employees,id',
            'violation_date' => 'required|date',
            'valid_from' => 'required|date',
            'valid_until' => 'required|date|after_or_equal:valid_from',
            'violation_type' => 'required|string|max:50',
            'document_number' => 'nullable|string|max:100',
            'contract_number' => 'nullable|string|max:100',
            'description' => 'required|string',
            'violation_points' => 'nullable|string',
            'legal_basis' => 'nullable|string',
            'evidence_base64' => 'nullable|string',
            'status' => 'in:active,expired,revoked',
            'notes' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // Auto-generate document number if empty
        $docNumber = $request->document_number;
        if (empty($docNumber)) {
            $count = HrisViolation::where('tenant_id', $tenantId)->count() + 1;
            $month = date('m', strtotime($request->violation_date));
            $year = date('Y', strtotime($request->violation_date));
            $docNumber = sprintf("SP/%s/%s/%03d", $year, $month, $count);
        }

        $violation = HrisViolation::create([
            'tenant_id' => $tenantId,
            'employee_id' => $request->employee_id,
            'violation_date' => $request->violation_date,
            'valid_from' => $request->valid_from,
            'valid_until' => $request->valid_until,
            'violation_type' => $request->violation_type,
            'document_number' => $docNumber,
            'contract_number' => $request->contract_number,
            'description' => $request->description,
            'violation_points' => $request->violation_points,
            'legal_basis' => $request->legal_basis,
            'evidence_base64' => $request->evidence_base64,
            'status' => $request->status ?? 'active',
            'issued_by' => $request->user()->id,
            'notes' => $request->notes,
        ]);

        return response()->json($violation->load(['employee.user', 'issuer']), 201);
    }

    public function update(Request $request, $id)
    {
        $tenantId = $this->getTenantId($request);
        $violation = HrisViolation::where('tenant_id', $tenantId)->findOrFail($id);

        $validator = Validator::make($request->all(), [
            'violation_date' => 'required|date',
            'valid_from' => 'required|date',
            'valid_until' => 'required|date|after_or_equal:valid_from',
            'violation_type' => 'required|string|max:50',
            'document_number' => 'nullable|string|max:100',
            'contract_number' => 'nullable|string|max:100',
            'description' => 'required|string',
            'violation_points' => 'nullable|string',
            'legal_basis' => 'nullable|string',
            'evidence_base64' => 'nullable|string',
            'status' => 'in:active,expired,revoked',
            'notes' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $updateData = $request->only([
            'violation_date', 'valid_from', 'valid_until', 'violation_type',
            'document_number', 'contract_number', 'description', 'violation_points',
            'legal_basis', 'status', 'notes'
        ]);

        if ($request->filled('evidence_base64')) {
            $updateData['evidence_base64'] = $request->evidence_base64;
        }

        $violation->update($updateData);
        return response()->json($violation->load(['employee.user', 'issuer']));
    }

    public function revoke(Request $request, $id)
    {
        $tenantId = $this->getTenantId($request);
        $violation = HrisViolation::where('tenant_id', $tenantId)->findOrFail($id);

        $violation->status = 'revoked';
        $violation->notes = ($violation->notes ? $violation->notes . "
" : '') . "Dicabut oleh HR pada " . now()->format('d/m/Y H:i');
        $violation->save();

        return response()->json([
            'message' => 'Surat Peringatan / Pelanggaran berhasil dicabut.',
            'data' => $violation->load(['employee.user', 'issuer'])
        ]);
    }

    public function destroy(Request $request, $id)
    {
        $tenantId = $this->getTenantId($request);
        $violation = HrisViolation::where('tenant_id', $tenantId)->findOrFail($id);
        $violation->delete();

        return response()->json(['message' => 'Data pelanggaran berhasil dihapus.']);
    }
}
