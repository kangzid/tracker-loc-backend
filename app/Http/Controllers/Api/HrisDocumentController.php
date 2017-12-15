<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\HrisDocument;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class HrisDocumentController extends Controller
{
    private function getTenantId(Request $request)
    {
        $user = $request->user();
        return $user->isAdmin() ? $user->id : ($user->admin_id ?? $user->id);
    }

    public function index(Request $request)
    {
        $tenantId = $this->getTenantId($request);
        $query = HrisDocument::where('tenant_id', $tenantId)
            ->with(['employee.user', 'verifier']);

        if ($request->filled('employee_id')) {
            $query->where('employee_id', $request->employee_id);
        }

        if ($request->filled('category')) {
            $query->where('category', $request->category);
        }

        if ($request->filled('is_verified')) {
            $query->where('is_verified', filter_var($request->is_verified, FILTER_VALIDATE_BOOLEAN));
        }

        if ($request->filled('is_original_stored')) {
            $query->where('is_original_stored', filter_var($request->is_original_stored, FILTER_VALIDATE_BOOLEAN));
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('category', 'like', "%{$search}%")
                  ->orWhere('physical_location', 'like', "%{$search}%")
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
        $query = HrisDocument::where('tenant_id', $tenantId);

        $totalDocs = (clone $query)->count();
        $verifiedDocs = (clone $query)->where('is_verified', true)->count();
        $unverifiedDocs = (clone $query)->where('is_verified', false)->count();
        $originalStoredDocs = (clone $query)->where('is_original_stored', true)->count();

        return response()->json([
            'total_documents' => $totalDocs,
            'verified_count' => $verifiedDocs,
            'unverified_count' => $unverifiedDocs,
            'original_stored_count' => $originalStoredDocs,
        ]);
    }

    public function store(Request $request)
    {
        $tenantId = $this->getTenantId($request);

        $validator = Validator::make($request->all(), [
            'employee_id' => 'required|exists:employees,id',
            'title' => 'required|string|max:150',
            'category' => 'required|string|max:50',
            'file_base64' => 'required|string',
            'file_name' => 'nullable|string|max:255',
            'file_type' => 'nullable|string|max:50',
            'file_size_kb' => 'nullable|integer',
            'physical_location' => 'nullable|string|max:255',
            'is_original_stored' => 'boolean',
            'notes' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $doc = HrisDocument::create([
            'tenant_id' => $tenantId,
            'employee_id' => $request->employee_id,
            'title' => $request->title,
            'category' => $request->category,
            'file_base64' => $request->file_base64,
            'file_name' => $request->file_name ?? ($request->title . '.jpg'),
            'file_type' => $request->file_type ?? 'image/jpeg',
            'file_size_kb' => $request->file_size_kb ?? (int)(strlen($request->file_base64) * 0.75 / 1024),
            'physical_location' => $request->physical_location,
            'is_original_stored' => $request->is_original_stored ?? false,
            'is_verified' => false,
            'notes' => $request->notes,
        ]);

        return response()->json($doc->load(['employee.user', 'verifier']), 201);
    }

    public function update(Request $request, $id)
    {
        $tenantId = $this->getTenantId($request);
        $doc = HrisDocument::where('tenant_id', $tenantId)->findOrFail($id);

        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:150',
            'category' => 'required|string|max:50',
            'file_base64' => 'nullable|string',
            'physical_location' => 'nullable|string|max:255',
            'is_original_stored' => 'boolean',
            'notes' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $updateData = [
            'title' => $request->title,
            'category' => $request->category,
            'physical_location' => $request->physical_location,
            'is_original_stored' => $request->is_original_stored ?? $doc->is_original_stored,
            'notes' => $request->notes,
        ];

        if ($request->filled('file_base64')) {
            $updateData['file_base64'] = $request->file_base64;
            $updateData['file_size_kb'] = (int)(strlen($request->file_base64) * 0.75 / 1024);
        }

        $doc->update($updateData);
        return response()->json($doc->load(['employee.user', 'verifier']));
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
