<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\HrisResignation;
use App\Models\Employee;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class HrisResignationController extends Controller
{
    private function getTenantId(Request $request)
    {
        return $request->user()->isAdmin() ? $request->user()->id : ($request->user()->admin_id ?? $request->user()->id);
    }

    public function index(Request $request)
    {
        $tenantId = $this->getTenantId($request);
        $query = HrisResignation::with(['employee.user', 'creator'])
            ->where('tenant_id', $tenantId);

        if ($request->filled('employee_id')) {
            $query->where('employee_id', $request->employee_id);
        }

        if ($request->filled('category')) {
            $query->where('category', $request->category);
        }

        if ($request->filled('search')) {
            $s = $request->search;
            $query->where(function($q) use ($s) {
                $q->where('resignation_number', 'like', "%{$s}%")
                  ->orWhere('category', 'like', "%{$s}%")
                  ->orWhere('reason', 'like', "%{$s}%")
                  ->orWhereHas('employee.user', function($uq) use ($s) {
                      $uq->where('name', 'like', "%{$s}%");
                  })
                  ->orWhereHas('employee', function($eq) use ($s) {
                      $eq->where('employee_id', 'like', "%{$s}%");
                  });
            });
        }

        $items = $query->orderBy('resignation_date', 'desc')->orderBy('id', 'desc')->get();

        return response()->json([
            'status' => 'success',
            'data' => $items
        ]);
    }

    public function summary(Request $request)
    {
        $tenantId = $this->getTenantId($request);
        $total = HrisResignation::where('tenant_id', $tenantId)->count();
        $voluntary = HrisResignation::where('tenant_id', $tenantId)->where('category', 'like', '%Sukarela%')->count();
        $contractEnd = HrisResignation::where('tenant_id', $tenantId)->where('category', 'like', '%Kontrak%')->count();
        $phk = HrisResignation::where('tenant_id', $tenantId)->where('category', 'like', '%PHK%')->orWhere('category', 'like', '%Pemutusan%')->count();

        return response()->json([
            'status' => 'success',
            'data' => [
                'total_resignations' => $total,
                'voluntary_count' => $voluntary,
                'contract_end_count' => $contractEnd,
                'phk_count' => $phk,
            ]
        ]);
    }

    public function store(Request $request)
    {
        $tenantId = $this->getTenantId($request);

        $request->validate([
            'employee_id' => 'required|exists:employees,id',
            'category' => 'required|string',
            'resignation_date' => 'required|date',
            'reason' => 'nullable|string',
            'document_base64' => 'nullable|string',
            'document_name' => 'nullable|string',
        ]);

        $emp = Employee::findOrFail($request->employee_id);

        $count = HrisResignation::where('tenant_id', $tenantId)->count() + 1;
        $resignationNumber = 'RSG/' . date('Ym') . '/' . str_pad($count, 4, '0', STR_PAD_LEFT);

        $filePath = null;
        $fileName = $request->document_name;

        
        $filePath = null;
        $fileName = null;

        if ($request->hasFile('document_file')) {
            $file = $request->file('document_file');
            $fileName = $file->getClientOriginalName();
            $rawBinary = file_get_contents($file->getRealPath());

            $encryptedData = Crypt::encrypt($rawBinary);
            $storedName = 'resign_' . time() . '_' . Str::random(8) . '.enc';
            $filePath = "private/tenants/{$tenantId}/resignations/{$storedName}";
            Storage::disk('local')->put($filePath, $encryptedData);

        } elseif ($request->filled('document_base64')) {
            $rawBase64 = preg_replace('#^data:[^;]+;base64,#i', '', $request->document_base64);
            $rawBinary = base64_decode($rawBase64);

            $encryptedData = Crypt::encrypt($rawBinary);
            $storedName = 'resign_' . time() . '_' . Str::random(8) . '.enc';
            $filePath = "private/tenants/{$tenantId}/resignations/{$storedName}";
            Storage::disk('local')->put($filePath, $encryptedData);
            $fileName = $request->document_name ?: ('surat_resign_' . time() . '.pdf');
        }


        $resignation = HrisResignation::create([
            'tenant_id' => $tenantId,
            'employee_id' => $request->employee_id,
            'resignation_number' => $resignationNumber,
            'category' => $request->category,
            'resignation_date' => $request->resignation_date,
            'last_working_date' => $request->last_working_date ?: $request->resignation_date,
            'reason' => $request->reason,
            'document_path' => $filePath,
            'document_name' => $fileName,
            'document_base64' => $request->document_base64,
            'status' => 'approved',
            'created_by' => $request->user()->id,
        ]);

        // Automatically set employee status to inactive and record exit date!
        $emp->is_active = false;
        $emp->exit_date = $request->resignation_date;
        $emp->save();
        if ($emp->user) {
            $emp->user->is_active = false;
            $emp->user->save();
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Data pengunduran diri / resign berhasil dicatat dan status karyawan otomatis menjadi Non-Aktif.',
            'data' => $resignation->load('employee.user')
        ], 201);
    }

    public function show(Request $request, $id)
    {
        $tenantId = $this->getTenantId($request);
        $resignation = HrisResignation::with(['employee.user', 'creator'])
            ->where('tenant_id', $tenantId)
            ->findOrFail($id);

        return response()->json(['status' => 'success', 'data' => $resignation]);
    }

    public function destroy(Request $request, $id)
    {
        $tenantId = $this->getTenantId($request);
        $resignation = HrisResignation::where('tenant_id', $tenantId)->findOrFail($id);

        // Optionally reactivate employee if deleted
        $emp = Employee::find($resignation->employee_id);
        if ($emp) {
            $emp->is_active = true;
            $emp->exit_date = null;
            $emp->save();
        }

        if ($resignation->document_path && Storage::disk('public')->exists($resignation->document_path)) {
            Storage::disk('public')->delete($resignation->document_path);
        }

        $resignation->delete();

        return response()->json(['status' => 'success', 'message' => 'Data resign berhasil dihapus dan status karyawan telah diaktifkan kembali.']);
    }

    public function previewDocument(Request $request, $id)
    {
        $tenantId = $this->getTenantId($request);
        $resignation = HrisResignation::where('tenant_id', $tenantId)->findOrFail($id);

        if (!$resignation->document_path || !Storage::disk('local')->exists($resignation->document_path)) {
            if ($resignation->document_base64) {
                $raw = base64_decode(preg_replace('#^data:[^;]+;base64,#i', '', $resignation->document_base64));
                return response($raw, 200, [
                    'Content-Type' => 'application/pdf',
                    'Content-Disposition' => 'inline; filename="' . ($resignation->document_name ?: 'Surat_Resign.pdf') . '"'
                ]);
            }
            return response()->json(['message' => 'Berkas surat resign tidak ditemukan.'], 404);
        }

        $encrypted = Storage::disk('local')->get($resignation->document_path);
        try {
            $decrypted = Crypt::decrypt($encrypted);
        } catch (\Exception $e) {
            $decrypted = $encrypted;
        }

        return response($decrypted, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="' . ($resignation->document_name ?: 'Surat_Resign.pdf') . '"',
            'Cache-Control' => 'private, no-cache, no-store, must-revalidate',
        ]);
    }

    public function downloadDocument(Request $request, $id)
    {
        $tenantId = $this->getTenantId($request);
        $resignation = HrisResignation::where('tenant_id', $tenantId)->findOrFail($id);

        if (!$resignation->document_path || !Storage::disk('local')->exists($resignation->document_path)) {
            if ($resignation->document_base64) {
                $raw = base64_decode(preg_replace('#^data:[^;]+;base64,#i', '', $resignation->document_base64));
                return response($raw, 200, [
                    'Content-Type' => 'application/pdf',
                    'Content-Disposition' => 'attachment; filename="' . ($resignation->document_name ?: 'Surat_Resign.pdf') . '"'
                ]);
            }
            return response()->json(['message' => 'Berkas surat resign tidak ditemukan.'], 404);
        }

        $encrypted = Storage::disk('local')->get($resignation->document_path);
        try {
            $decrypted = Crypt::decrypt($encrypted);
        } catch (\Exception $e) {
            $decrypted = $encrypted;
        }

        return response($decrypted, 200, [
            'Content-Type' => 'application/octet-stream',
            'Content-Disposition' => 'attachment; filename="' . ($resignation->document_name ?: 'Surat_Resign.pdf') . '"',
        ]);
    }

}
