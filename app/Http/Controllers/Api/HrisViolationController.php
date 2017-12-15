<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\HrisViolation;
use App\Models\HrisViolationType;
use App\Services\EncryptedStorageService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class HrisViolationController extends Controller
{
    private function getTenantId(Request $request)
    {
        $user = $request->user();
        return $user->role === 'superadmin' ? ($user->tenant_id ?? 1) : ($user->admin_id ?? $user->id);
    }

    public function summary(Request $request)
    {
        $tenantId = $this->getTenantId($request);
        $today = Carbon::today()->toDateString();

        $violations = HrisViolation::where('tenant_id', $tenantId)->get();

        $total = $violations->count();
        $active = 0;
        $expired = 0;
        $spCount = 0;
        $teguranCount = 0;

        foreach ($violations as $v) {
            $isDateActive = (!$v->valid_until || $v->valid_until >= $today);
            if ($v->status === 'active' && $isDateActive) {
                $active++;
            } else {
                $expired++;
            }

            $type = strtolower($v->violation_type ?? '');
            if (str_contains($type, 'surat peringatan') || str_contains($type, 'sp')) {
                $spCount++;
            } elseif (str_contains($type, 'teguran')) {
                $teguranCount++;
            }
        }

        return response()->json([
            'status' => 'success',
            'data' => [
                'total_violations' => $total,
                'active_count' => $active,
                'expired_count' => $expired,
                'sp_count' => $spCount,
                'teguran_count' => $teguranCount,
            ]
        ]);
    }

    public function index(Request $request)
    {
        $tenantId = $this->getTenantId($request);

        $query = HrisViolation::where('tenant_id', $tenantId)
            ->with(['employee.user', 'issuer'])
            ->orderBy('id', 'desc');

        if ($request->has('status') && $request->status && $request->status !== 'all') {
            $query->where('status', $request->status);
        }

        if ($request->has('violation_type') && $request->violation_type && $request->violation_type !== 'all') {
            $query->where('violation_type', $request->violation_type);
        }

        if ($request->has('employee_id') && $request->employee_id) {
            $query->where('employee_id', $request->employee_id);
        }

        if ($request->has('search') && $request->search) {
            $s = $request->search;
            $query->where(function ($q) use ($s) {
                $q->where('document_number', 'like', "%{$s}%")
                    ->orWhere('description', 'like', "%{$s}%")
                    ->orWhereHas('employee.user', function ($uq) use ($s) {
                        $uq->where('name', 'like', "%{$s}%");
                    });
            });
        }

        return response()->json($query->paginate($request->get('per_page', 15)));
    }

    public function store(Request $request)
    {
        $tenantId = $this->getTenantId($request);

        $validator = Validator::make($request->all(), [
            'employee_id' => 'required|exists:employees,id',
            'violation_type' => 'required|string|max:100',
            'violation_date' => 'required|date',
            'valid_from' => 'required|date',
            'valid_until' => 'nullable|date',
            'description' => 'required|string',
            'legal_basis' => 'nullable|string',
            'evidence_file' => 'nullable|file|max:10240',
            'evidence_base64' => 'nullable|string',
            'evidence_name' => 'nullable|string|max:255',
            'document_number' => 'nullable|string|max:100',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validasi gagal',
                'errors' => $validator->errors()
            ], 422);
        }

        // Auto-calculate valid_until if empty based on type duration
        $validUntil = $request->valid_until;
        if (!$validUntil) {
            $typeModel = HrisViolationType::where('tenant_id', $tenantId)
                ->where('name', $request->violation_type)
                ->first();
            $months = $typeModel ? (int)$typeModel->default_duration_months : 6;
            $validUntil = Carbon::parse($request->valid_from)->addMonths($months)->toDateString();
        }

        // Auto generate document number if empty
        $docNum = $request->document_number;
        if (!$docNum) {
            $docNum = 'SP/' . date('Ym') . '/' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
        }

        $evidencePath = null;
        $evidenceName = $request->evidence_name ?: 'Bukti_Pelanggaran.pdf';

        if ($request->hasFile('evidence_file')) {
            $stored = EncryptedStorageService::storeEncrypted(
                $request->file('evidence_file'),
                $tenantId,
                'violations',
                'evidence_' . $request->employee_id
            );
            $evidencePath = $stored['path'];
            $evidenceName = $stored['name'];
        } elseif ($request->filled('evidence_base64')) {
            $stored = EncryptedStorageService::storeEncrypted(
                $request->evidence_base64,
                $tenantId,
                'violations',
                'evidence_' . $request->employee_id,
                $evidenceName
            );
            $evidencePath = $stored['path'];
            $evidenceName = $stored['name'];
        }

        $violation = HrisViolation::create([
            'tenant_id' => $tenantId,
            'employee_id' => $request->employee_id,
            'violation_date' => $request->violation_date,
            'valid_from' => $request->valid_from,
            'valid_until' => $validUntil,
            'violation_type' => $request->violation_type,
            'document_number' => $docNum,
            'contract_number' => $request->contract_number,
            'description' => $request->description,
            'violation_points' => $request->violation_points,
            'legal_basis' => $request->legal_basis,
            'evidence_path' => $evidencePath,
            'evidence_name' => $evidenceName,
            'status' => 'active',
            'issued_by' => $request->user()->id,
            'notes' => $request->notes,
        ]);

        return response()->json($violation->load(['employee.user', 'issuer']), 201);
    }

    public function show(Request $request, $id)
    {
        $tenantId = $this->getTenantId($request);
        $viol = HrisViolation::where('tenant_id', $tenantId)
            ->with(['employee.user', 'issuer'])
            ->findOrFail($id);

        return response()->json($viol);
    }

    public function update(Request $request, $id)
    {
        $tenantId = $this->getTenantId($request);
        $viol = HrisViolation::where('tenant_id', $tenantId)->findOrFail($id);

        $validator = Validator::make($request->all(), [
            'violation_type' => 'required|string|max:100',
            'violation_date' => 'required|date',
            'valid_from' => 'required|date',
            'valid_until' => 'nullable|date',
            'description' => 'required|string',
            'legal_basis' => 'nullable|string',
            'evidence_file' => 'nullable|file|max:10240',
            'evidence_base64' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        if ($request->hasFile('evidence_file')) {
            $stored = EncryptedStorageService::storeEncrypted(
                $request->file('evidence_file'),
                $tenantId,
                'violations',
                'evidence_' . $viol->employee_id
            );
            $viol->evidence_path = $stored['path'];
            $viol->evidence_name = $stored['name'];
        } elseif ($request->filled('evidence_base64')) {
            $stored = EncryptedStorageService::storeEncrypted(
                $request->evidence_base64,
                $tenantId,
                'violations',
                'evidence_' . $viol->employee_id,
                $request->evidence_name ?: ($viol->evidence_name ?: 'Bukti.pdf')
            );
            $viol->evidence_path = $stored['path'];
            $viol->evidence_name = $stored['name'];
        }

        $viol->violation_type = $request->violation_type;
        $viol->violation_date = $request->violation_date;
        $viol->valid_from = $request->valid_from;
        if ($request->filled('valid_until')) {
            $viol->valid_until = $request->valid_until;
        }
        $viol->description = $request->description;
        $viol->legal_basis = $request->legal_basis;
        $viol->notes = $request->notes;
        $viol->save();

        return response()->json($viol->load(['employee.user', 'issuer']));
    }

    public function revoke(Request $request, $id)
    {
        $tenantId = $this->getTenantId($request);
        $viol = HrisViolation::where('tenant_id', $tenantId)->findOrFail($id);

        $viol->status = 'revoked';
        $viol->notes = ($viol->notes ? $viol->notes . "\n" : "") . "Dicabut pada " . date('d/m/Y') . ": " . ($request->reason ?: 'Pemulihan status kedisiplinan');
        $viol->save();

        return response()->json($viol->load(['employee.user', 'issuer']));
    }

    public function destroy(Request $request, $id)
    {
        $tenantId = $this->getTenantId($request);
        $viol = HrisViolation::where('tenant_id', $tenantId)->findOrFail($id);

        if (!empty($viol->evidence_path)) {
            EncryptedStorageService::deleteFile($viol->evidence_path);
        }

        $viol->delete();
        return response()->json(['message' => 'Data sanksi / surat peringatan berhasil dihapus.']);
    }

    public function previewEvidence(Request $request, $id)
    {
        $tenantId = $this->getTenantId($request);
        $viol = HrisViolation::where('tenant_id', $tenantId)->findOrFail($id);
        if (!$viol->evidence_path) {
            return response()->json(['message' => 'Berkas bukti tidak ditemukan.'], 404);
        }
        return EncryptedStorageService::streamResponse($viol->evidence_path, $viol->evidence_name, false);
    }

    public function downloadEvidence(Request $request, $id)
    {
        $tenantId = $this->getTenantId($request);
        $viol = HrisViolation::where('tenant_id', $tenantId)->findOrFail($id);
        if (!$viol->evidence_path) {
            return response()->json(['message' => 'Berkas bukti tidak ditemukan.'], 404);
        }
        return EncryptedStorageService::streamResponse($viol->evidence_path, $viol->evidence_name, true);
    }

    // MASTER VIOLATION TYPES
    public function getViolationTypes(Request $request)
    {
        $tenantId = $this->getTenantId($request);
        $types = HrisViolationType::where('tenant_id', $tenantId)->orderBy('id', 'asc')->get();

        // Seed default standard types if none exist
        if ($types->isEmpty()) {
            $defaults = [
                ['code' => 'TEGURAN_LISAN', 'name' => 'Teguran Lisan', 'default_duration_months' => 1, 'description' => 'Peringatan lisan atas kelalaian ringan'],
                ['code' => 'TEGURAN_TERTULIS', 'name' => 'Teguran Tertulis', 'default_duration_months' => 3, 'description' => 'Surat teguran resmi atas pelanggaran tata tertib'],
                ['code' => 'SP_1', 'name' => 'Surat Peringatan I (Pertama)', 'default_duration_months' => 6, 'description' => 'Surat Peringatan Pertama berlaku 6 bulan'],
                ['code' => 'SP_2', 'name' => 'Surat Peringatan II (Kedua)', 'default_duration_months' => 6, 'description' => 'Surat Peringatan Kedua berlaku 6 bulan'],
                ['code' => 'SP_3', 'name' => 'Surat Peringatan III (Terakhir)', 'default_duration_months' => 6, 'description' => 'Surat Peringatan Terakhir sebelum tindakan PHK'],
            ];
            foreach ($defaults as $d) {
                HrisViolationType::create([
                    'tenant_id' => $tenantId,
                    'code' => $d['code'],
                    'name' => $d['name'],
                    'default_duration_months' => $d['default_duration_months'],
                    'description' => $d['description'],
                    'is_active' => true,
                ]);
            }
            $types = HrisViolationType::where('tenant_id', $tenantId)->orderBy('id', 'asc')->get();
        }

        return response()->json($types);
    }

    public function storeViolationType(Request $request)
    {
        $tenantId = $this->getTenantId($request);

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:100',
            'code' => 'nullable|string|max:50',
            'default_duration_months' => 'nullable|integer',
            'validity_months' => 'nullable|integer',
            'description' => 'nullable|string',
            'is_active' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validasi gagal',
                'errors' => $validator->errors()
            ], 422);
        }

        $code = $request->code ?: strtoupper(str_replace(' ', '_', $request->name));
        $duration = $request->default_duration_months ?? $request->validity_months ?? 6;

        $type = HrisViolationType::updateOrCreate(
            ['tenant_id' => $tenantId, 'id' => $request->id],
            [
                'name' => $request->name,
                'code' => $code,
                'default_duration_months' => $duration,
                'description' => $request->description,
                'is_active' => $request->is_active ?? true,
            ]
        );

        return response()->json($type, 201);
    }

    public function deleteViolationType(Request $request, $id)
    {
        $tenantId = $this->getTenantId($request);
        $type = HrisViolationType::where('tenant_id', $tenantId)->findOrFail($id);
        $type->delete();
        return response()->json(['message' => 'Jenis surat peringatan / sanksi berhasil dihapus.']);
    }
}
