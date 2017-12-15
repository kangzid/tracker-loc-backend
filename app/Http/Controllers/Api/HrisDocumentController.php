<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\HrisDocument;
use App\Services\EncryptedStorageService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class HrisDocumentController extends Controller
{
    private function getTenantId(Request $request)
    {
        $user = $request->user();
        return $user->role === 'superadmin' ? ($user->tenant_id ?? 1) : ($user->admin_id ?? $user->id);
    }

    public function index(Request $request)
    {
        $tenantId = $this->getTenantId($request);

        $query = HrisDocument::with(['employee.user', 'verifier'])
            ->where('tenant_id', $tenantId)
            ->orderBy('id', 'desc');

        if ($request->has('category') && $request->category !== 'all') {
            $query->where('category', $request->category);
        }

        if ($request->has('is_verified')) {
            $query->where('is_verified', filter_var($request->is_verified, FILTER_VALIDATE_BOOLEAN));
        }

        if ($request->has('is_original_stored')) {
            $query->where('is_original_stored', filter_var($request->is_original_stored, FILTER_VALIDATE_BOOLEAN));
        }

        if ($request->has('employee_id') && $request->employee_id) {
            $query->where('employee_id', $request->employee_id);
        }

        if ($request->has('search') && $request->search) {
            $s = $request->search;
            $query->where(function ($q) use ($s) {
                $q->where('title', 'like', "%{$s}%")
                    ->orWhere('document_name', 'like', "%{$s}%")
                    ->orWhere('file_name', 'like', "%{$s}%")
                    ->orWhere('physical_location', 'like', "%{$s}%")
                    ->orWhereHas('employee.user', function ($uq) use ($s) {
                        $uq->where('name', 'like', "%{$s}%");
                    });
            });
        }

        return response()->json($query->paginate($request->get('per_page', 15)));
    }

    public function summary(Request $request)
    {
        $tenantId = $this->getTenantId($request);
        $total = HrisDocument::where('tenant_id', $tenantId)->count();
        $verified = HrisDocument::where('tenant_id', $tenantId)->where('is_verified', true)->count();
        $unverified = HrisDocument::where('tenant_id', $tenantId)->where('is_verified', false)->count();
        $physical = HrisDocument::where('tenant_id', $tenantId)->where('is_original_stored', true)->count();

        return response()->json([
            'status' => 'success',
            'data' => [
                'total_documents' => $total,
                'verified_count' => $verified,
                'unverified_count' => $unverified,
                'original_stored_count' => $physical,
            ]
        ]);
    }

    public function store(Request $request)
    {
        $tenantId = $this->getTenantId($request);

        $validator = Validator::make($request->all(), [
            'employee_id' => 'required|exists:employees,id',
            'title' => 'required|string|max:150',
            'category' => 'required|string|max:50',
            'file_base64' => 'nullable|string',
            'document_file' => 'nullable|file|max:10240', // max 10MB
            'file_name' => 'nullable|string|max:255',
            'file_type' => 'nullable|string|max:50',
            'physical_location' => 'nullable|string|max:255',
            'is_original_stored' => 'boolean',
            'notes' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $filePath = null;
        $fileName = $request->file_name ?? ($request->title . '.pdf');
        $fileType = $request->file_type ?? 'application/pdf';
        $fileSizeKb = 0;

        if ($request->hasFile('document_file')) {
            $stored = EncryptedStorageService::storeEncrypted($request->file('document_file'), $tenantId, 'documents', 'doc_' . $request->employee_id);
            $filePath = $stored['path'];
            $fileName = $stored['name'];
            $fileType = $stored['mime'];
            $fileSizeKb = (int) ($stored['size'] / 1024);
        } elseif ($request->filled('file_base64')) {
            $stored = EncryptedStorageService::storeEncrypted($request->file_base64, $tenantId, 'documents', 'doc_' . $request->employee_id, $fileName);
            $filePath = $stored['path'];
            $fileName = $stored['name'];
            $fileType = $stored['mime'];
            $fileSizeKb = (int) ($stored['size'] / 1024);
        }

        $doc = HrisDocument::create([
            'tenant_id' => $tenantId,
            'employee_id' => $request->employee_id,
            'title' => $request->title,
            'category' => $request->category,
            'document_name' => $fileName,
            'document_path' => $filePath,
            'file_name' => $fileName,
            'file_type' => $fileType,
            'file_size_kb' => $fileSizeKb,
            'physical_location' => $request->physical_location,
            'is_original_stored' => $request->is_original_stored ?? false,
            'is_verified' => false,
            'notes' => $request->notes,
        ]);

        return response()->json($doc->load(['employee.user', 'verifier']), 201);
    }

    public function show(Request $request, $id)
    {
        $tenantId = $this->getTenantId($request);
        $doc = HrisDocument::with(['employee.user', 'verifier'])
            ->where('tenant_id', $tenantId)
            ->findOrFail($id);

        return response()->json($doc);
    }

    public function preview(Request $request, $id)
    {
        $tenantId = $this->getTenantId($request);
        $doc = HrisDocument::where('tenant_id', $tenantId)->findOrFail($id);

        if (!$doc->document_path) {
            return response()->json(['message' => 'Berkas dokumen tidak ditemukan.'], 404);
        }

        return EncryptedStorageService::streamResponse($doc->document_path, $doc->document_name ?: $doc->file_name ?: $doc->title, false);
    }

    public function download(Request $request, $id)
    {
        $tenantId = $this->getTenantId($request);
        $doc = HrisDocument::where('tenant_id', $tenantId)->findOrFail($id);

        if (!$doc->document_path) {
            return response()->json(['message' => 'Berkas dokumen tidak ditemukan.'], 404);
        }

        return EncryptedStorageService::streamResponse($doc->document_path, $doc->document_name ?: $doc->file_name ?: $doc->title, true);
    }

    public function verify(Request $request, $id)
    {
        $tenantId = $this->getTenantId($request);
        $doc = HrisDocument::where('tenant_id', $tenantId)->findOrFail($id);

        $isVerified = $request->boolean('is_verified', true);
        $doc->is_verified = $isVerified;
        $doc->verified_by = $isVerified ? $request->user()->id : null;
        $doc->verified_at = $isVerified ? now() : null;
        $doc->save();

        return response()->json([
            'message' => $isVerified ? 'Dokumen berhasil diverifikasi.' : 'Status verifikasi dokumen dicabut.',
            'data' => $doc->load(['employee.user', 'verifier'])
        ]);
    }

    public function destroy(Request $request, $id)
    {
        $tenantId = $this->getTenantId($request);
        $doc = HrisDocument::where('tenant_id', $tenantId)->findOrFail($id);
        $doc->delete();
        return response()->json(['message' => 'Dokumen berhasil dihapus.']);
    }
}
