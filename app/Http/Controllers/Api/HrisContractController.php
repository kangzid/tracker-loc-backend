<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\HrisContract;
use App\Models\Employee;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class HrisContractController extends Controller
{
    private function getTenantId(Request $request)
    {
        return $request->user()->isAdmin() ? $request->user()->id : ($request->user()->admin_id ?? $request->user()->id);
    }

    public function index(Request $request)
    {
        $tenantId = $this->getTenantId($request);
        $query = HrisContract::with(['employee.user'])
            ->where('tenant_id', $tenantId);

        if ($request->filled('search')) {
            $s = $request->search;
            $query->where(function($q) use ($s) {
                $q->where('contract_number', 'like', "%{$s}%")
                  ->orWhere('document_number', 'like', "%{$s}%")
                  ->orWhere('position', 'like', "%{$s}%")
                  ->orWhere('department', 'like', "%{$s}%")
                  ->orWhereHas('employee.user', function($uq) use ($s) {
                      $uq->where('name', 'like', "%{$s}%");
                  });
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('contract_type')) {
            $query->where('contract_type', $request->contract_type);
        }

        if ($request->filled('employee_id')) {
            $query->where('employee_id', $request->employee_id);
        }

        $contracts = $query->orderBy('created_at', 'desc')->get();

        if ($request->filled('bank_name') && $request->filled('bank_account_number')) {
            \App\Models\HrisSalary::updateOrCreate(
                ['tenant_id' => $tenantId, 'employee_id' => $request->employee_id],
                [
                    'bank_name' => $request->bank_name,
                    'bank_account_number' => $request->bank_account_number,
                    'bank_account_holder' => $request->bank_account_holder ?: ($emp && $emp->user ? $emp->user->name : null),
                    'amount' => $request->basic_salary ?: 0,
                    'status' => 'active'
                ]
            );
        }
        return response()->json([
            'status' => 'success',
            'data' => $contracts
        ]);
    }

    public function summary(Request $request)
    {
        $tenantId = $this->getTenantId($request);
        $total = HrisContract::where('tenant_id', $tenantId)->count();
        $active = HrisContract::where('tenant_id', $tenantId)->where('status', 'active')->count();
        $pkwt = HrisContract::where('tenant_id', $tenantId)->where('contract_type', 'PKWT')->where('status', 'active')->count();
        $pkwtt = HrisContract::where('tenant_id', $tenantId)->where('contract_type', 'PKWTT')->where('status', 'active')->count();
        
        $expiringSoon = HrisContract::where('tenant_id', $tenantId)
            ->where('status', 'active')
            ->whereNotNull('end_date')
            ->whereBetween('end_date', [now(), now()->addDays(30)])
            ->count();

        return response()->json([
            'status' => 'success',
            'data' => [
                'total_contracts' => $total,
                'active_contracts' => $active,
                'pkwt_count' => $pkwt,
                'pkwtt_count' => $pkwtt,
                'expiring_soon_count' => $expiringSoon,
            ]
        ]);
    }

    public function store(Request $request)
    {
        $tenantId = $this->getTenantId($request);

        $request->validate([
            'employee_id' => 'required|exists:employees,id',
            'contract_date' => 'required|date',
            'start_date' => 'required|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'contract_type' => 'required|string',
            'department' => 'nullable|string',
            'position' => 'nullable|string',
            'basic_salary' => 'nullable|numeric',
            'allowance_extrafooding' => 'nullable|numeric',
            'allowance_fuel' => 'nullable|numeric',
            'allowance_communication' => 'nullable|numeric',
            'allowances_json' => 'nullable',
            'document_number' => 'nullable|string',
            'bank_name' => 'nullable|string',
            'bank_account_number' => 'nullable|string',
            'bank_account_holder' => 'nullable|string',
            'bank_name' => 'nullable|string',
            'bank_account_number' => 'nullable|string',
            'bank_account_holder' => 'nullable|string',
            'notes' => 'nullable|string',
            'contract_pdf' => 'nullable|file|mimes:pdf|max:10240',
            'document_pdf_base64' => 'nullable|string',
            'document_pdf_name' => 'nullable|string',
        ]);

        $count = HrisContract::where('tenant_id', $tenantId)->count() + 1;
        $contractNumber = 'CTR/' . date('Ym') . '/' . str_pad($count, 4, '0', STR_PAD_LEFT);

        $pdfPath = null;
        $pdfName = $request->document_pdf_name;
        $pdfBase64 = $request->document_pdf_base64;

        if ($request->hasFile('contract_pdf')) {
            $file = $request->file('contract_pdf');
            $pdfName = $file->getClientOriginalName();
            $tenantFolder = "tenants/{$tenantId}/contracts";
            $pdfPath = $file->store($tenantFolder, 'public');
            $pdfBase64 = 'data:application/pdf;base64,' . base64_encode(file_get_contents($file->getRealPath()));
        } elseif ($request->filled('document_pdf_base64')) {
            $rawBase64 = preg_replace('#^data:application/pdf;base64,#i', '', $request->document_pdf_base64);
            $fileName = 'contract_' . time() . '_' . Str::random(8) . '.pdf';
            $tenantFolder = "tenants/{$tenantId}/contracts";
            $filePath = "{$tenantFolder}/{$fileName}";
            
            Storage::disk('public')->put($filePath, base64_decode($rawBase64));
            $pdfPath = $filePath;
            if (!$pdfName) {
                $pdfName = $fileName;
            }
        }

        $emp = Employee::find($request->employee_id);
        $department = $request->department ?: ($emp ? $emp->department : null);
        $position = $request->position ?: ($emp ? $emp->position : null);

        $contract = HrisContract::create([
            'tenant_id' => $tenantId,
            'employee_id' => $request->employee_id,
            'contract_number' => $contractNumber,
            'document_number' => $request->document_number,
            'contract_type' => $request->contract_type,
            'contract_date' => $request->contract_date,
            'start_date' => $request->start_date,
            'end_date' => $request->end_date,
            'department' => $department,
            'position' => $position,
            'basic_salary' => $request->basic_salary ?: 0,
            'allowance_extrafooding' => $request->allowance_extrafooding ?: 0,
            'allowance_fuel' => $request->allowance_fuel ?: 0,
            'allowance_communication' => $request->allowance_communication ?: 0,
            'allowances_json' => is_array($request->allowances_json) ? $request->allowances_json : json_decode($request->allowances_json, true),
            'bank_name' => $request->bank_name,
            'bank_account_number' => $request->bank_account_number,
            'bank_account_holder' => $request->bank_account_holder ?: ($emp && $emp->user ? $emp->user->name : null),
            'document_pdf_path' => $pdfPath,
            'document_pdf_name' => $pdfName,
            'document_pdf_base64' => $pdfBase64,
            'status' => 'active',
            'notes' => $request->notes,
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Kontrak kerja berhasil diterbitkan.',
            'data' => $contract->load('employee.user')
        ], 201);
    }

    public function show(Request $request, $id)
    {
        $tenantId = $this->getTenantId($request);
        $contract = HrisContract::with('employee.user')
            ->where('tenant_id', $tenantId)
            ->findOrFail($id);

        return response()->json([
            'status' => 'success',
            'data' => $contract
        ]);
    }

    public function update(Request $request, $id)
    {
        $tenantId = $this->getTenantId($request);
        $contract = HrisContract::where('tenant_id', $tenantId)->findOrFail($id);

        $request->validate([
            'contract_date' => 'sometimes|date',
            'start_date' => 'sometimes|date',
            'end_date' => 'nullable|date',
            'contract_type' => 'sometimes|string',
            'department' => 'nullable|string',
            'position' => 'nullable|string',
            'basic_salary' => 'nullable|numeric',
            'allowance_extrafooding' => 'nullable|numeric',
            'allowance_fuel' => 'nullable|numeric',
            'allowance_communication' => 'nullable|numeric',
            'document_number' => 'nullable|string',
            'status' => 'sometimes|in:active,expired,terminated,renewed',
            'notes' => 'nullable|string',
            'contract_pdf' => 'nullable|file|mimes:pdf|max:10240',
            'document_pdf_base64' => 'nullable|string',
            'document_pdf_name' => 'nullable|string',
        ]);

        if ($request->hasFile('contract_pdf')) {
            $file = $request->file('contract_pdf');
            $pdfName = $file->getClientOriginalName();
            $tenantFolder = "tenants/{$tenantId}/contracts";
            $pdfPath = $file->store($tenantFolder, 'public');
            $pdfBase64 = 'data:application/pdf;base64,' . base64_encode(file_get_contents($file->getRealPath()));

            $contract->document_pdf_path = $pdfPath;
            $contract->document_pdf_name = $pdfName;
            $contract->document_pdf_base64 = $pdfBase64;
        } elseif ($request->filled('document_pdf_base64')) {
            $rawBase64 = preg_replace('#^data:application/pdf;base64,#i', '', $request->document_pdf_base64);
            $fileName = 'contract_' . time() . '_' . Str::random(8) . '.pdf';
            $tenantFolder = "tenants/{$tenantId}/contracts";
            $filePath = "{$tenantFolder}/{$fileName}";
            
            Storage::disk('public')->put($filePath, base64_decode($rawBase64));
            $contract->document_pdf_path = $filePath;
            $contract->document_pdf_name = $request->document_pdf_name ?: $fileName;
            $contract->document_pdf_base64 = $request->document_pdf_base64;
        }

        $contract->update($request->only([
            'contract_date', 'start_date', 'end_date', 'contract_type',
            'department', 'position', 'basic_salary', 'allowance_extrafooding',
            'allowance_fuel', 'allowance_communication', 'document_number',
            'status', 'notes', 'allowances_json', 'bank_name', 'bank_account_number', 'bank_account_holder'
        ]));

        return response()->json([
            'status' => 'success',
            'message' => 'Data kontrak berhasil diperbarui.',
            'data' => $contract->load('employee.user')
        ]);
    }

    public function destroy(Request $request, $id)
    {
        $tenantId = $this->getTenantId($request);
        $contract = HrisContract::where('tenant_id', $tenantId)->findOrFail($id);

        if ($contract->document_pdf_path && Storage::disk('public')->exists($contract->document_pdf_path)) {
            Storage::disk('public')->delete($contract->document_pdf_path);
        }

        $contract->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Kontrak kerja berhasil dihapus.'
        ]);
    }

    public function downloadPdf(Request $request, $id)
    {
        $tenantId = $this->getTenantId($request);
        $contract = HrisContract::where('tenant_id', $tenantId)->findOrFail($id);

        if (!$contract->document_pdf_path || !Storage::disk('public')->exists($contract->document_pdf_path)) {
            if ($contract->document_pdf_base64) {
                $pdfData = base64_decode(preg_replace('#^data:application/pdf;base64,#i', '', $contract->document_pdf_base64));
                return response($pdfData, 200, [
                    'Content-Type' => 'application/pdf',
                    'Content-Disposition' => 'inline; filename="' . ($contract->document_pdf_name ?: 'kontrak.pdf') . '"'
                ]);
            }
            return response()->json(['message' => 'Berkas PDF kontrak tidak ditemukan di storage.'], 404);
        }

        return Storage::disk('public')->response($contract->document_pdf_path, $contract->document_pdf_name ?: 'kontrak.pdf');
    }
}
